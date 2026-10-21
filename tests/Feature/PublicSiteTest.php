<?php

namespace Tests\Feature;

use App\Enums\BusinessStatus;
use App\Models\Area;
use App\Models\Business;
use App\Models\Category;
use App\Models\Municipality;
use App\Models\Offer;
use App\Models\Page;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    private function business(array $attrs = [], string $state = 'published'): Business
    {
        return Business::factory()->{$state}()->create($attrs);
    }

    public function test_arabic_homepage_loads_in_rtl_with_seo_tags(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('dir="rtl"', false)->assertSee('lang="ar"', false)
            ->assertSee('hreflang="en"', false)->assertSee('hreflang="x-default"', false)
            ->assertSee('"@type":"WebSite"', false)
            ->assertSee('دليل قطر');
    }

    public function test_english_homepage_loads_in_ltr(): void
    {
        $this->get('/en')->assertOk()->assertSee('dir="ltr"', false)->assertSee('Discover the best businesses');
    }

    public function test_language_switch_preserves_path_and_query(): void
    {
        $this->business(['slug' => 'pearl-cafe']);

        $this->get('/business/pearl-cafe')->assertOk()->assertSee(url('/en/business/pearl-cafe'), false);
        $this->get('/en/search?q=coffee&verified=1')->assertOk()->assertSee(url('/search').'?q=coffee&amp;verified=1', false);
    }

    public function test_search_returns_published_results_from_postgres_with_arabic_normalisation(): void
    {
        $this->business(['name_ar' => 'مقهى الأصالة', 'name_en' => 'Asala Coffee']);
        $this->business(['name_ar' => 'مقهى مسودة', 'name_en' => 'Draft Coffee'], 'pending');

        // "اصاله" (no hamza, haa instead of taa marbuta) must match "الأصالة"
        $this->get('/search?q='.urlencode('اصاله'))->assertOk()->assertSee('مقهى الأصالة')->assertDontSee('مقهى مسودة');
        $this->get('/en/search?q=coffee')->assertOk()->assertSee('Asala Coffee')->assertDontSee('Draft Coffee');
        // typo tolerance
        $this->get('/en/search?q=cofee')->assertOk()->assertSee('Asala Coffee');
    }

    public function test_category_and_location_filters(): void
    {
        $cafes = Category::query()->where('slug', 'cafes')->firstOrFail();
        $legal = Category::query()->where('slug', 'legal')->firstOrFail();
        $lusail = Area::query()->where('slug', 'lusail')->firstOrFail();
        $doha = Municipality::query()->where('slug', 'doha')->firstOrFail();

        $this->business(['name_en' => 'Lusail Cafe One', 'category_id' => $cafes->id, 'municipality_id' => $lusail->municipality_id, 'area_id' => $lusail->id]);
        $this->business(['name_en' => 'Doha Law Two', 'category_id' => $legal->id, 'municipality_id' => $doha->id]);

        // Parent category includes sub-category listings
        $this->get('/en/categories/restaurants-cafes')->assertOk()->assertSee('Lusail Cafe One')->assertDontSee('Doha Law Two');
        $this->get('/en/search?category=legal')->assertOk()->assertSee('Doha Law Two')->assertDontSee('Lusail Cafe One');
        $this->get('/en/search?municipality=al-daayen&area=lusail')->assertOk()->assertSee('Lusail Cafe One')->assertDontSee('Doha Law Two');
        $this->get('/en/locations/doha')->assertOk()->assertSee('Doha Law Two')->assertDontSee('Lusail Cafe One');
    }

    public function test_verified_filter_and_suggestions_endpoint(): void
    {
        $this->business(['name_en' => 'Verified Clinic', 'is_verified' => true]);
        $this->business(['name_en' => 'Plain Clinic']);

        $this->get('/en/search?q=clinic&verified=1')->assertOk()->assertSee('Verified Clinic')->assertDontSee('Plain Clinic');
        $this->getJson('/en/search/suggest?q=clinic')->assertOk()->assertJsonCount(2, 'businesses');
    }

    public function test_unpublished_profiles_are_hidden_from_the_public(): void
    {
        foreach (['draft', 'pending', 'rejected', 'suspended', 'archived'] as $status) {
            $b = $this->business(['status' => BusinessStatus::from($status), 'published_at' => null], 'published');
            $this->get('/business/'.$b->slug)->assertNotFound();
        }
        $live = $this->business(['name_en' => 'Live Business']);
        $this->get('/en/business/'.$live->slug)->assertOk()->assertSee('Live Business')->assertSee('"@type":"LocalBusiness"', false);
    }

    public function test_profile_never_invents_ratings_or_contacts(): void
    {
        $b = $this->business(['name_en' => 'Quiet Shop', 'phone' => null, 'email' => null, 'website' => null, 'whatsapp' => null]);
        $this->get('/en/business/'.$b->slug)->assertOk()
            ->assertDontSee('aggregateRating', false)
            ->assertSee(__('directory.na', [], 'en'))
            ->assertDontSee(__('directory.press_to_view', [], 'en'));
    }

    public function test_listing_report_and_contact_message_are_stored(): void
    {
        $b = $this->business();
        $this->post('/en/business/'.$b->slug.'/report', ['reason' => 'incorrect_info', 'details' => 'Wrong hours'])->assertRedirect();
        $this->assertDatabaseHas('listing_reports', ['business_id' => $b->id, 'reason' => 'incorrect_info', 'status' => 'open']);

        $this->post('/en/contact', ['name' => 'Sara', 'email' => 'sara@example.com', 'subject' => 'Hello', 'message' => 'I would like to know more.'])->assertRedirect();
        $this->assertDatabaseHas('contact_messages', ['email' => 'sara@example.com', 'status' => 'new']);

        // Honeypot rejects bots
        $this->post('/en/contact', ['name' => 'Bot', 'email' => 'bot@example.com', 'subject' => 'x', 'message' => 'spam spam spam', 'company_website' => 'http://spam'])->assertSessionHasErrors('company_website');
    }

    public function test_favorites_require_verified_login_and_toggle(): void
    {
        $b = $this->business();
        $this->postJson('/en/business/'.$b->slug.'/favorite')->assertUnauthorized();

        $user = $this->makeUser();
        $this->actingAs($user)->postJson('/en/business/'.$b->slug.'/favorite')->assertOk()->assertJson(['favorited' => true]);
        $this->assertSame(1, $b->fresh()->favorites_count);
        $this->actingAs($user)->postJson('/en/business/'.$b->slug.'/favorite')->assertOk()->assertJson(['favorited' => false]);
        $this->assertSame(0, $b->fresh()->favorites_count);
    }

    public function test_reviews_are_moderated_and_owners_cannot_review_themselves(): void
    {
        $owner = $this->makeUser();
        $b = $this->business(['owner_id' => $owner->id]);
        $reviewer = $this->makeUser();

        $this->actingAs($reviewer)->post('/en/business/'.$b->slug.'/reviews', ['rating' => 5, 'body' => 'Excellent service and a lovely place to visit.'])->assertRedirect();
        $this->assertDatabaseHas('reviews', ['business_id' => $b->id, 'user_id' => $reviewer->id, 'status' => 'pending']);
        $this->assertNull($b->fresh()->rating_avg, 'Pending reviews must not affect the rating');

        // Duplicate review blocked
        $this->actingAs($reviewer)->post('/en/business/'.$b->slug.'/reviews', ['rating' => 4, 'body' => 'Trying to review twice which is not allowed.'])->assertSessionHas('error');

        $this->actingAs($owner)->post('/en/business/'.$b->slug.'/reviews', ['rating' => 5, 'body' => 'My own business is the best in town.'])->assertForbidden();
    }

    public function test_claims_only_for_unowned_published_listings_and_no_duplicates(): void
    {
        $b = $this->business(['owner_id' => null]);
        $user = $this->makeUser();
        $payload = ['claimant_name' => 'Ali', 'phone' => '+97450000000', 'email' => 'ali@example.com', 'terms' => '1'];

        $this->actingAs($user)->post('/en/business/'.$b->slug.'/claim', $payload)->assertRedirect();
        $this->assertDatabaseHas('business_claim_requests', ['business_id' => $b->id, 'user_id' => $user->id, 'status' => 'pending']);
        $this->actingAs($user)->post('/en/business/'.$b->slug.'/claim', $payload)->assertSessionHas('error');
        $this->assertSame(1, $b->claimRequests()->count());

        $owned = $this->business(['owner_id' => $this->makeUser()->id]);
        $this->actingAs($user)->get('/en/business/'.$owned->slug.'/claim')->assertNotFound();
    }

    public function test_expired_offers_are_excluded(): void
    {
        $b = $this->business();
        Offer::factory()->create(['business_id' => $b->id, 'title_en' => 'Live Deal']);
        Offer::factory()->create(['business_id' => $b->id, 'title_en' => 'Old Deal', 'starts_at' => now()->subMonth(), 'ends_at' => now()->subDay()]);
        Offer::factory()->create(['business_id' => $b->id, 'title_en' => 'Pending Deal', 'status' => 'pending']);

        $this->get('/en/offers')->assertOk()->assertSee('Live Deal')->assertDontSee('Old Deal')->assertDontSee('Pending Deal');
    }

    public function test_sitemap_excludes_unpublished_and_demo_listings_and_includes_alternates(): void
    {
        $live = $this->business(['is_demo' => false, 'slug' => 'real-live-biz']);
        $this->business(['is_demo' => true, 'slug' => 'demo-biz']);
        $this->business(['is_demo' => false, 'slug' => 'pending-biz'], 'pending');

        $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<sitemapindex', false)
            ->assertSee(url('/sitemaps/pages.xml'), false)
            ->assertSee(url('/sitemaps/businesses-1.xml'), false);

        $this->get('/sitemaps/businesses-1.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(url('/business/real-live-biz'), false)
            ->assertSee(url('/en/business/real-live-biz'), false)
            ->assertSee('hreflang="en"', false)
            ->assertDontSee('demo-biz')->assertDontSee('pending-biz');
    }

    public function test_sitemap_is_well_formed_xml(): void
    {
        $this->business(['is_demo' => false, 'slug' => 'well-formed-biz']);

        foreach (['/sitemap.xml', '/sitemaps/pages.xml', '/sitemaps/taxonomy.xml', '/sitemaps/businesses-1.xml'] as $path) {
            $xml = $this->get($path)->assertOk()->getContent();
            $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
            $this->assertNotFalse(simplexml_load_string($xml), "$path must parse as XML.");
        }
        $this->get('/sitemaps/businesses-9.xml')->assertNotFound();
    }

    public function test_robots_blocks_everything_outside_production(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');
    }

    public function test_security_headers_are_present(): void
    {
        $this->get('/en')->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_localized_404_pages(): void
    {
        $this->get('/en/does-not-exist')->assertNotFound()->assertSee('Page not found');
        $this->get('/does-not-exist')->assertNotFound()->assertSee('الصفحة غير موجودة');
    }

    public function test_static_pages_render_markdown_safely(): void
    {
        Page::query()->where('slug', 'about')->update(['body_en' => 'Hello <script>alert(1)</script> **bold**']);
        $this->get('/en/about')->assertOk()->assertSee('<strong>bold</strong>', false)->assertDontSee('<script>alert(1)</script>', false);
    }
}
