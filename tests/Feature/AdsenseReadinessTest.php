<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Page;
use App\Services\SettingsService;
use App\Support\AdsenseReadiness;
use App\Support\ListingQuality;
use App\Support\Permissions;
use App\Support\Sitemap;
use Database\Seeders\Content\PolicyPages;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** The pages and content rules Google AdSense reviews, and the readiness report. */
class AdsenseReadinessTest extends TestCase
{
    public function test_every_policy_page_is_published_in_both_languages_and_linked_from_the_footer(): void
    {
        foreach (['cookies' => 'Cookie Policy', 'disclaimer' => 'Disclaimer', 'editorial' => 'Editorial & Listing Policy', 'advertise' => 'Advertise with Us'] as $route => $title) {
            $this->get(route('ar.'.$route))->assertOk()->assertSee(Page::query()->where('slug', $route)->value('title_ar'));
            $this->get(route('en.'.$route))->assertOk()->assertSee($title);
        }

        $home = $this->get(route('home'))->assertOk();
        foreach (['privacy', 'terms', 'cookies', 'disclaimer', 'editorial', 'advertise'] as $route) {
            $home->assertSee(route($route), false);
        }

        $privacy = Page::query()->where('slug', 'privacy')->firstOrFail();
        $this->assertStringContainsString(PolicyPages::PRIVACY_MARKER_EN, $privacy->body_en, 'The privacy policy explains the visitor statistics.');
        $this->assertStringContainsString('adssettings.google.com', $privacy->body_en);
    }

    public function test_the_policy_migration_adds_missing_pages_once_and_keeps_edited_ones(): void
    {
        Page::query()->where('slug', 'disclaimer')->delete();
        Page::query()->where('slug', 'cookies')->update(['body_en' => 'Edited by the team']);
        $migration = require database_path('migrations/2026_10_26_110000_add_policy_pages.php');

        $migration->up();
        $migration->up();

        $this->assertSame(1, Page::query()->where('slug', 'disclaimer')->count());
        $this->assertSame('Edited by the team', Page::query()->where('slug', 'cookies')->value('body_en'));
        $this->assertSame(1, substr_count((string) Page::query()->where('slug', 'privacy')->value('body_en'), PolicyPages::PRIVACY_MARKER_EN));
    }

    public function test_thin_listings_stay_on_the_site_but_out_of_search_and_the_sitemap(): void
    {
        $thin = Business::factory()->published()->create(['name_en' => 'Thin Trading', 'description_en' => null, 'description_ar' => null, 'website' => null, 'address_en' => null, 'address_ar' => null,
            'latitude' => null, 'longitude' => null, 'logo_path' => null, 'google_place_id' => null, 'opening_hours_text' => null, 'reviews_count' => 0, 'is_demo' => false]);
        $rich = Business::factory()->published()->create(['name_en' => 'Rich Trading', 'description_en' => str_repeat('A complete description of this company and its services. ', 3), 'is_demo' => false]);

        $this->assertFalse(ListingQuality::isRich($thin));
        $this->assertTrue(ListingQuality::isRich($rich));
        $this->assertSame([$rich->id], ListingQuality::scopeRich(Business::query()->whereIn('id', [$thin->id, $rich->id]))->pluck('id')->all(), 'PHP and SQL apply the same rule.');

        $this->get(route('business.show', $thin))->assertOk()->assertSee('noindex', false);
        Cache::flush();
        $this->assertStringNotContainsString($thin->slug, Sitemap::part('businesses-1') ?? '');
        $this->assertStringContainsString($rich->slug, Sitemap::part('businesses-1') ?? '');

        app(SettingsService::class)->set('seo.noindex_thin_listings', false);
        $this->assertFalse(ListingQuality::enabled());
    }

    public function test_the_report_shows_what_to_fix_and_where(): void
    {
        $admin = $this->makeUser(Permissions::SUPER_ADMIN);
        Business::factory()->published()->create(['is_demo' => true]);

        $report = app(AdsenseReadiness::class)->report(fresh: true);
        $items = collect($report['groups'])->flatMap(fn ($g) => $g['items'])->keyBy('key');
        $this->assertSame('fail', $items['publisher_id']['status']);
        $this->assertSame('fail', $items['demo_listings']['status']);
        $this->assertSame('pass', $items['page_cookies']['status']);
        $this->assertSame('pass', $items['ads_disclosure']['status']);
        $this->assertSame('pass', $items['thin_listings']['status']);
        $this->assertSame('warn', $items['search_console']['status']);

        app(SettingsService::class)->set('integrations.adsense_client', 'ca-pub-1234567890123456');
        app(SettingsService::class)->set('integrations.search_console_verified', true);
        $items = collect(app(AdsenseReadiness::class)->report(fresh: true)['groups'])->flatMap(fn ($g) => $g['items'])->keyBy('key');
        $this->assertSame('pass', $items['publisher_id']['status']);
        $this->assertSame('pass', $items['ads_txt']['status']);
        $this->assertSame('pass', $items['search_console']['status']);

        $this->actingAs($admin)->get(route('admin.adsense.index', ['refresh' => 1]))->assertOk()
            ->assertSee(__('adsense.checks.demo_listings.title'))
            ->assertSee(__('adsense.console.domain_title'))
            ->assertSee(route('admin.businesses.index', ['demo' => 1]), false);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee(__('adsense.widget_title'));
    }

    public function test_only_settings_managers_see_the_report(): void
    {
        $this->actingAs($this->makeUser(Permissions::MODERATOR))->get(route('admin.adsense.index'))->assertForbidden();
    }
}
