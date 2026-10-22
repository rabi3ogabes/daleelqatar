<?php

namespace Tests\Feature\Growth;

use App\Enums\BusinessEventType;
use App\Models\Business;
use App\Models\BusinessEvent;
use App\Notifications\MonthlyInsightsReport;
use App\Support\Insights\Impressions;
use App\Support\Permissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InsightsAndPosterTest extends TestCase
{
    private const UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile Safari/604.1';

    private function owned(): array
    {
        $owner = $this->makeUser(Permissions::BUSINESS_OWNER);
        $business = Business::factory()->published()->create(['owner_id' => $owner->id, 'is_demo' => false]);

        return [$owner, $business];
    }

    public function test_results_pages_carry_a_signed_list_that_counts_search_appearances(): void
    {
        [, $business] = $this->owned();
        $payload = Impressions::payload([$business->id]);

        $this->get('/en/search?q='.urlencode($business->name_en).'&exact=1')->assertOk()->assertSee('data-impressions="'.$payload.'"', false);

        $this->withHeader('User-Agent', self::UA)->postJson('/t/impressions', ['l' => $payload])->assertOk()->assertJson(['ok' => true]);
        $this->withHeader('User-Agent', self::UA)->postJson('/t/impressions', ['l' => $payload]);
        $this->assertSame(2, (int) DB::table('business_daily_stats')->where('business_id', $business->id)->value('impressions'));

        // Forged lists and robots are ignored.
        $this->withHeader('User-Agent', self::UA)->postJson('/t/impressions', ['l' => $business->id.'~0000000000aaaaaaaaaa'])->assertJson(['ok' => false]);
        $this->withHeader('User-Agent', 'Googlebot/2.1')->postJson('/t/impressions', ['l' => $payload])->assertJson(['ok' => false]);
        $this->assertSame(2, (int) DB::table('business_daily_stats')->where('business_id', $business->id)->value('impressions'));
    }

    public function test_qr_scan_is_counted_and_opens_the_listing_in_the_visitors_language(): void
    {
        [, $business] = $this->owned();

        $this->withHeaders(['User-Agent' => self::UA, 'Accept-Language' => 'en-GB,en;q=0.9'])->get('/qr/'.$business->slug)
            ->assertRedirect('/en/business/'.$business->slug.'?utm_source=qr&utm_medium=poster');
        $this->withHeaders(['User-Agent' => self::UA, 'Accept-Language' => 'ar-QA'])->get('/qr/'.$business->slug)
            ->assertRedirect('/business/'.$business->slug.'?utm_source=qr&utm_medium=poster');
        $this->assertSame(2, (int) DB::table('business_daily_stats')->where('business_id', $business->id)->value('qr_scans'));

        $this->get('/qr/no-such-business')->assertRedirect();
    }

    public function test_owner_prints_a_poster_and_downloads_the_qr_code(): void
    {
        [$owner, $business] = $this->owned();

        $this->actingAs($owner)->get('/en/account/businesses/'.$business->uuid.'/poster')->assertOk()
            ->assertSee('<svg', false)->assertSee('/qr/'.$business->slug)->assertSee($business->name_en);
        $this->actingAs($owner)->get('/en/account/businesses/'.$business->uuid.'/poster?format=card')->assertOk();
        $this->actingAs($owner)->get('/en/account/businesses/'.$business->uuid.'/qr.svg')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');

        $stranger = $this->makeUser(Permissions::BUSINESS_OWNER);
        $this->actingAs($stranger)->get('/en/account/businesses/'.$business->uuid.'/poster')->assertNotFound();
        $this->actingAs($stranger)->get('/en/account/businesses/'.$business->uuid.'/qr.svg')->assertNotFound();

        $this->actingAs($this->makeUser(Permissions::ADMIN))->get('/en/admin/businesses/'.$business->id.'/poster')->assertOk();
    }

    public function test_insights_compare_periods_from_real_events(): void
    {
        [$owner, $business] = $this->owned();
        foreach (range(1, 6) as $i) {
            BusinessEvent::query()->create(['business_id' => $business->id, 'type' => BusinessEventType::View, 'created_at' => now()->subDays(2)]);
        }
        foreach (range(1, 3) as $i) {
            BusinessEvent::query()->create(['business_id' => $business->id, 'type' => BusinessEventType::View, 'created_at' => now()->subDays(40)]);
        }
        BusinessEvent::query()->create(['business_id' => $business->id, 'type' => BusinessEventType::WhatsApp, 'created_at' => now()->subDay()]);
        DB::table('business_daily_stats')->insert(['business_id' => $business->id, 'day' => now()->subDay()->toDateString(), 'impressions' => 40, 'qr_scans' => 2]);

        $this->actingAs($owner)->get('/en/account/businesses/'.$business->uuid.'/insights')->assertOk()
            ->assertSee(__('insights.metric.impressions', [], 'en'))
            ->assertSee('+100%') // 6 views vs 3
            ->assertSee('40');

        foreach (['month', 'last_month', '90d'] as $period) {
            $this->actingAs($owner)->get('/en/account/businesses/'.$business->uuid.'/insights?period='.$period)->assertOk();
        }
        $this->actingAs($this->makeUser(Permissions::BUSINESS_OWNER))->get('/en/account/businesses/'.$business->uuid.'/insights')->assertNotFound();
    }

    public function test_monthly_report_goes_once_to_each_owner_who_wants_it(): void
    {
        Notification::fake();
        [$owner] = $this->owned();
        [$quiet] = $this->owned();
        $quiet->forceFill(['notification_preferences' => ['monthly_report' => false]])->save();

        $this->artisan('daleel:insights-report')->assertSuccessful();
        $this->artisan('daleel:insights-report')->assertSuccessful();

        Notification::assertSentToTimes($owner, MonthlyInsightsReport::class, 1);
        Notification::assertNotSentTo($quiet, MonthlyInsightsReport::class);
        $this->assertSame(2, DB::table('insight_reports')->count());
    }

    public function test_monthly_report_mail_renders(): void
    {
        [$owner, $business] = $this->owned();
        $mail = (new MonthlyInsightsReport('October 2026', [[
            'name' => $business->name_en, 'uuid' => $business->uuid, 'views' => 120, 'views_change' => 15,
            'impressions' => 900, 'contacts' => 14, 'quotes' => 2, 'tip' => 'Add photos',
        ]]))->toMail($owner);

        $this->assertStringContainsString('October 2026', $mail->subject);
        $this->assertStringContainsString('/insights', $mail->actionUrl);
        $this->assertStringContainsString('+15%', implode(' ', $mail->introLines));
    }
}
