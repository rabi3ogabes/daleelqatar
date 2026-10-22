<?php

namespace Tests\Feature;

use App\Http\Controllers\VisitController;
use App\Models\Business;
use App\Services\SettingsService;
use App\Support\Analytics\PageKey;
use App\Support\Analytics\Visit;
use App\Support\Permissions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** First-party visitor analytics: collection, the public view counter and the admin reports. */
class VisitorAnalyticsTest extends TestCase
{
    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1';

    private const WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';

    private function visit(array $body = [], string $ua = self::IPHONE, array $headers = [])
    {
        return $this->withHeaders($headers + ['User-Agent' => $ua, 'x-vercel-ip-country' => 'QA', 'x-vercel-ip-city' => 'Doha'])
            ->postJson('/t/view', $body + ['p' => '/categories', 't' => 'Categories | Daleel Qatar', 'v' => 'visitorAAAA1111', 's' => 'sessionAAAA1111']);
    }

    public function test_a_page_view_is_recorded_without_personal_data(): void
    {
        $key = 'categories.index';
        $response = $this->visit(['k' => $key, 'g' => PageKey::sign($key), 'r' => 'https://www.google.com/', 'n' => true])->assertOk();

        $row = DB::table('page_views')->first();
        $this->assertSame('QA', $row->country);
        $this->assertSame('Doha', $row->city);
        $this->assertSame('mobile', $row->device);
        $this->assertSame('Safari', $row->browser);
        $this->assertSame('iOS', $row->os);
        $this->assertSame('search', $row->channel);
        $this->assertSame('Google', $row->referrer);
        $this->assertSame('ar', $row->locale);
        $this->assertTrue((bool) $row->is_new);
        $this->assertSame($key, $row->page_key);
        $this->assertStringNotContainsString('127.0.0.1', json_encode($row), 'No IP address is stored.');
        $this->assertStringNotContainsString('iPhone', json_encode($row), 'No user-agent string is stored.');
        $this->assertNotSame('visitorAAAA1111', $row->visitor, 'The browser token is stored only as a hash.');

        $this->assertSame(1, $response->json('views'));
        $this->assertFalse($response->json('show'), 'Below the minimum the counter stays hidden.');
        $this->assertEmpty($response->headers->getCookies(), 'The collector sets no cookie, so cached pages stay cacheable.');
    }

    public function test_bots_and_unsigned_pages_are_not_counted(): void
    {
        $this->visit(['k' => 'categories.index', 'g' => PageKey::sign('categories.index')], 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')->assertOk();
        $this->assertSame(0, DB::table('page_views')->count());

        $this->visit(['k' => 'business:999', 'g' => 'forged-signature'])->assertOk()->assertJsonPath('views', null);
        $this->assertNull(DB::table('page_views')->value('page_key'), 'A page key that the site did not sign is ignored.');
        $this->assertSame(0, DB::table('page_stats')->count());
    }

    public function test_the_public_counter_counts_each_visitor_once_and_keeps_a_listings_old_views(): void
    {
        app(SettingsService::class)->set('analytics.public_counts_min', 10);
        $business = Business::factory()->published()->create(['views_count' => 41, 'is_demo' => false]);
        $key = PageKey::forBusiness($business->id);
        $sig = PageKey::sign($key);

        $this->visit(['k' => $key, 'g' => $sig, 'p' => '/business/'.$business->slug])->assertJsonPath('views', 42)->assertJsonPath('show', true);
        $this->visit(['k' => $key, 'g' => $sig, 'p' => '/business/'.$business->slug])->assertJsonPath('views', 42);
        $this->visit(['k' => $key, 'g' => $sig, 'p' => '/en/business/'.$business->slug, 'v' => 'visitorBBBB2222'])->assertJsonPath('views', 43);

        $this->assertSame(3, DB::table('page_views')->where('page_key', $key)->count(), 'Every view is in the report…');
        $this->assertSame(43, (int) DB::table('page_stats')->where('page_key', $key)->value('views'), '…the public counter counts people.');
        $this->assertSame('en', DB::table('page_views')->latest('id')->value('locale'));
    }

    public function test_pings_measure_time_on_page_only_with_the_right_token(): void
    {
        $id = $this->visit()->json('id');

        $this->postJson('/t/ping', ['id' => $id, 'token' => 'wrong', 'e' => 50])->assertForbidden();
        $this->postJson('/t/ping', ['id' => $id, 'token' => VisitController::token($id), 'e' => 75])->assertOk();
        $this->postJson('/t/ping', ['id' => $id, 'token' => VisitController::token($id), 'e' => 20])->assertOk();

        $this->assertSame(75, (int) DB::table('page_views')->where('id', $id)->value('engaged_seconds'), 'Time on page never goes backwards.');
    }

    public function test_public_pages_carry_a_signed_page_key_and_a_counter(): void
    {
        $business = Business::factory()->published()->create(['is_demo' => false]);

        $html = $this->get(route('business.show', $business))->assertOk()->getContent();
        $key = PageKey::forBusiness($business->id);
        $this->assertStringContainsString('data-page="'.$key.'"', $html);
        $this->assertStringContainsString('data-page-sig="'.PageKey::sign($key).'"', $html);
        $this->assertStringContainsString('data-page-views', $html);

        $this->assertStringContainsString('data-page="categories.index"', $this->get(route('categories.index'))->getContent());
        $this->assertStringNotContainsString('data-page=', $this->get(route('login'))->getContent(), 'Account pages are not counted.');
    }

    public function test_sources_devices_and_countries_are_classified(): void
    {
        $this->assertSame(['channel' => 'social', 'referrer' => 'Instagram'], Visit::source('https://l.instagram.com/?u=x', null, 'daleelqatar.com'));
        $this->assertSame(['channel' => 'internal', 'referrer' => null], Visit::source('https://www.daleelqatar.com/en', null, 'daleelqatar.com'));
        $this->assertSame('campaign', Visit::source('https://news.example.com', 'newsletter', 'daleelqatar.com')['channel']);
        $this->assertSame('referral', Visit::source('https://qatarliving.com/x', null, 'daleelqatar.com')['channel']);
        $this->assertSame('desktop', Visit::agent(self::WINDOWS)['device']);
        $this->assertSame('Chrome', Visit::agent(self::WINDOWS)['browser']);
        $this->assertSame('tablet', Visit::agent('Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X)')['device']);
        $this->assertSame('🇶🇦', Visit::flag('QA'));
        $this->assertSame('Qatar', Visit::countryName('QA', 'en'));
        $this->assertSame('قطر', Visit::countryName('QA', 'ar'));
    }

    public function test_admins_see_real_time_and_period_reports(): void
    {
        $admin = $this->makeUser(Permissions::ADMIN);
        $key = 'categories.index';
        $this->visit(['k' => $key, 'g' => PageKey::sign($key), 'r' => 'https://www.google.com/']);
        $this->visit(['p' => '/about', 't' => 'About us | Daleel Qatar'], self::WINDOWS, ['x-vercel-ip-country' => 'SA']);

        $this->actingAs($admin)->getJson(route('admin.analytics.realtime'))->assertOk()
            ->assertJsonPath('active', 1)
            ->assertJsonCount(30, 'minutes');

        $this->actingAs($admin)->get(route('admin.analytics.index', ['range' => 'today']))->assertOk()
            ->assertSee(__('analytics.realtime.title'))
            ->assertSee('Categories')
            ->assertSee('About us')
            ->assertSee(\Locale::getDisplayRegion('-SA', 'ar'));

        $this->actingAs($admin)->get(route('admin.analytics.index', ['tab' => 'directory']))->assertOk()->assertSee(__('admin.analytics.top_queries'));
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee(__('analytics.live'));
    }

    public function test_reports_are_private(): void
    {
        $this->actingAs($this->makeUser(Permissions::USER))->get(route('admin.analytics.index'))->assertForbidden();
        $this->actingAs($this->makeUser(Permissions::USER))->getJson(route('admin.analytics.realtime'))->assertForbidden();
    }
}
