<?php

namespace Tests\Feature\Directory;

use App\Models\Business;
use App\Models\BusinessEvent;
use App\Services\SettingsService;
use App\Support\Integrations;
use App\Support\Permissions;
use Tests\TestCase;

class PublicFeaturesTest extends TestCase
{
    private function business(array $attrs = [], string $state = 'published'): Business
    {
        return Business::factory()->{$state}()->create($attrs);
    }

    private function setting(string $key, mixed $value): void
    {
        app(SettingsService::class)->set($key, $value);
    }

    public function test_contact_details_are_hidden_from_the_html_and_revealed_on_request(): void
    {
        $b = $this->business(['slug' => 'secret-phone', 'phone' => '+974 4412 3456', 'email' => 'hello@secret.qa', 'whatsapp' => null]);

        $this->get('/en/business/secret-phone')->assertOk()
            ->assertSee(__('directory.press_to_view', [], 'en'))
            ->assertDontSee('4412 3456')->assertDontSee('44123456')->assertDontSee('hello@secret.qa')
            ->assertDontSee('"telephone"', false);

        $this->get('/en/business/secret-phone/reveal/phone')->assertNotFound();
        $this->getJson('/en/business/secret-phone/reveal/phone', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertJsonPath('items.0.value', '+974 4412 3456')
            ->assertJsonPath('items.0.href', 'tel:+97444123456')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->getJson('/en/business/secret-phone/reveal/email', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertJsonPath('items.0.value', 'hello@secret.qa');

        $this->assertTrue(BusinessEvent::query()->where('business_id', $b->id)->where('type', 'phone_reveal')->exists());
    }

    public function test_missing_details_show_na(): void
    {
        $this->business(['slug' => 'bare-listing', 'phone' => null, 'whatsapp' => null, 'email' => null, 'website' => null, 'address_en' => null, 'address_ar' => null]);

        $this->get('/en/business/bare-listing')->assertOk()->assertSee('N/A');
    }

    public function test_profile_has_share_buttons_for_whatsapp_facebook_x_and_messenger(): void
    {
        $this->business(['slug' => 'share-me']);

        $this->get('/en/business/share-me')->assertOk()
            ->assertSee('api.whatsapp.com/send', false)
            ->assertSee('facebook.com/sharer', false)
            ->assertSee('twitter.com/intent/tweet', false)
            ->assertSee('Messenger')->assertSee('Instagram');
    }

    public function test_view_beacon_counts_views_without_a_session(): void
    {
        $b = $this->business(['slug' => 'beacon-biz']);

        $this->post('/en/business/beacon-biz/viewed')->assertNoContent();

        $this->assertSame(1, (int) $b->refresh()->views_count);
    }

    public function test_map_page_and_points(): void
    {
        $this->business(['slug' => 'on-map', 'name_en' => 'Mapped Cafe', 'latitude' => 25.2854, 'longitude' => 51.531, 'geo_precision' => 'exact']);
        $this->business(['slug' => 'pending-map', 'name_en' => 'Hidden Cafe', 'latitude' => 25.3, 'longitude' => 51.5], 'pending');

        $this->get('/en/map')->assertOk()->assertSee('data-dq-map', false)->assertSee(route('en.map.data'), false);
        $this->get('/map')->assertOk();

        $json = $this->getJson('/en/map/data.json')->assertOk()->assertHeader('X-Robots-Tag', 'noindex')->json();
        $names = array_column($json['points'], 3);
        $this->assertContains('Mapped Cafe', $names);
        $this->assertNotContains('Hidden Cafe', $names);
    }

    public function test_az_directory_lists_businesses_by_letter(): void
    {
        $this->business(['name_en' => 'Zebra Logistics', 'name_ar' => 'زيبرا للخدمات اللوجستية', 'slug' => 'zebra-logistics']);
        $this->business(['name_en' => '7 Seas Trading', 'name_ar' => '7 Seas Trading', 'slug' => 'seven-seas']);

        $this->get('/en/directory')->assertOk()->assertSee(url('/en/directory/Z'), false);
        $this->get('/en/directory/Z')->assertOk()->assertSee('Zebra Logistics');
        $this->get('/en/directory/z')->assertRedirect(url('/en/directory/Z'));
        $this->get('/en/directory/0-9')->assertOk()->assertSee('7 Seas Trading');
        $this->get('/directory/'.rawurlencode('ز'))->assertOk()->assertSee('زيبرا للخدمات اللوجستية');
        $this->get('/en/directory/ZZ')->assertNotFound();
    }

    public function test_ads_txt_indexnow_key_and_csrf_endpoints(): void
    {
        $this->get('/ads.txt')->assertNotFound();
        $this->setting('integrations.adsense_client', 'ca-pub-1234567890123456');
        $this->get('/ads.txt')->assertOk()->assertSee('google.com, pub-1234567890123456, DIRECT, f08c47fec0942fa0');

        $this->get('/indexnow.txt')->assertOk()->assertSee(Integrations::indexNowKey());
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', Integrations::indexNowKey());

        $this->get('/csrf-token')->assertNotFound();
        $this->getJson('/csrf-token', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_google_tags_render_only_when_configured(): void
    {
        config(['services.integrations.enabled' => true]);
        $this->get('/en')->assertOk()->assertDontSee('googletagmanager.com', false)->assertDontSee('google-site-verification', false);

        $this->setting('integrations.ga4_id', 'G-ABC123XYZ9');
        $this->setting('integrations.google_verification', 'abcDEF123456_-verify');
        $this->setting('integrations.adsense_client', 'ca-pub-1234567890123456');

        $html = $this->get('/en')->assertOk()
            ->assertSee('<meta name="google-site-verification" content="abcDEF123456_-verify">', false)
            ->assertSee('<meta name="google-adsense-account" content="ca-pub-1234567890123456">', false)
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-ABC123XYZ9', false)
            ->assertSee("gtag('consent', 'default'", false)
            ->assertSee(__('directory.consent.accept', [], 'en'))
            // Ads only load on indexable pages, and indexing is always off outside production.
            ->assertDontSee('adsbygoogle.js', false)
            ->getContent();
        $this->assertStringContainsString("'strict-dynamic'", (string) $this->get('/en')->headers->get('Content-Security-Policy'));
        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*nonce=)(?![^>]*type="application\/ld\+json")[^>]*>/', $html, 'Every script tag must carry the CSP nonce.');
    }

    public function test_admin_can_paste_a_full_verification_meta_tag(): void
    {
        $admin = $this->makeUser(Permissions::SUPER_ADMIN);

        $this->actingAs($admin)->get(route('admin.settings.edit', 'integrations'))->assertOk()->assertSee('G-XXXXXXXXXX');
        $this->actingAs($admin)->put(route('admin.settings.update', 'integrations'), ['s' => [
            'integrations__google_verification' => '<meta name="google-site-verification" content="Tok3n_abcdefghij" />',
            'integrations__ga4_id' => 'G-ABCDE12345',
            'integrations__adsense_client' => '',
            'integrations__google_cache_hours' => 24,
            'integrations__enrichment_daily' => 0,
        ]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Tok3n_abcdefghij', setting('integrations.google_verification'));

        $this->actingAs($admin)->put(route('admin.settings.update', 'integrations'), ['s' => ['integrations__ga4_id' => 'UA-1234', 'integrations__google_cache_hours' => 24, 'integrations__enrichment_daily' => 0]])
            ->assertSessionHasErrors('s.integrations__ga4_id');
    }

    public function test_public_pages_are_cached_at_the_edge_only_for_cookieless_guests(): void
    {
        config(['cache.edge.enabled' => true]);
        $this->business(['slug' => 'cached-biz']);

        $response = $this->get('/en/business/cached-biz')->assertOk();
        $this->assertStringContainsString('s-maxage=600', (string) $response->headers->get('Cache-Control'));
        $this->assertContains('Cookie', $response->getVary());
        $this->assertSame([], $response->headers->getCookies(), 'Cached pages must not set cookies.');
        $response->assertSee('<html data-edge="1"', false);

        $withCookie = $this->withUnencryptedCookie('dq_seen', '1')->get('/en/business/cached-biz')->assertOk();
        $this->assertStringNotContainsString('s-maxage', (string) $withCookie->headers->get('Cache-Control'));

        $this->flushHeaders();
        $user = $this->actingAs($this->makeUser())->get('/en/business/cached-biz')->assertOk();
        $this->assertStringNotContainsString('s-maxage', (string) $user->headers->get('Cache-Control'));

        // Account and admin pages are never cached.
        $this->assertStringNotContainsString('s-maxage', (string) $this->get('/en/login')->headers->get('Cache-Control'));
    }
}
