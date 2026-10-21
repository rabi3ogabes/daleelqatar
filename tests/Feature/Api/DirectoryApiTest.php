<?php

namespace Tests\Feature\Api;

use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\Category;
use App\Models\Municipality;
use App\Models\Offer;

class DirectoryApiTest extends ApiTestCase
{
    public function test_search_returns_only_published_businesses_with_pagination_meta(): void
    {
        $this->publishedBusiness(['name_en' => 'Visible Cafe']);
        Business::factory()->create(['name_en' => 'Draft Cafe']);
        Business::factory()->pending()->create(['name_en' => 'Pending Cafe']);

        $response = $this->getJson('/api/v1/businesses?per_page=5')->assertOk()
            ->assertJsonStructure(['data' => [['id', 'slug', 'name', 'category', 'location', 'rating', 'is_verified', 'is_sample']], 'links', 'meta' => ['current_page', 'per_page', 'total', 'last_page', 'filters']]);

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame(5, $response->json('meta.per_page'));
        $this->assertSame('Visible Cafe', $this->withHeader('Accept-Language', 'en')->getJson('/api/v1/businesses')->json('data.0.name'));
    }

    public function test_category_filter_includes_subcategories(): void
    {
        $this->publishedBusiness(['name_en' => 'Sub Cafe', 'category_id' => Category::query()->where('slug', 'cafes')->value('id')]);
        $this->publishedBusiness(['name_en' => 'Lawyers', 'category_id' => Category::query()->where('slug', 'legal')->value('id')]);

        $names = collect($this->getJson('/api/v1/businesses?category=restaurants-cafes', ['Accept-Language' => 'en'])->assertOk()->json('data'))->pluck('name');

        $this->assertSame(['Sub Cafe'], $names->all());
    }

    public function test_municipality_and_verified_filters(): void
    {
        $this->publishedBusiness(['name_en' => 'Doha Verified', 'is_verified' => true]);
        $this->publishedBusiness(['name_en' => 'Doha Plain']);
        $this->publishedBusiness(['name_en' => 'Wakrah Spot', 'municipality_id' => Municipality::query()->where('slug', 'al-wakrah')->value('id')]);

        $en = ['Accept-Language' => 'en'];
        $this->assertCount(2, $this->getJson('/api/v1/businesses?municipality=doha', $en)->json('data'));
        $this->assertSame(['Doha Verified'], collect($this->getJson('/api/v1/businesses?municipality=doha&verified=true', $en)->json('data'))->pluck('name')->all());
        $this->assertSame(['Wakrah Spot'], collect($this->getJson('/api/v1/businesses?municipality=al-wakrah', $en)->json('data'))->pluck('name')->all());
    }

    public function test_keyword_search_uses_postgres_full_text(): void
    {
        $this->publishedBusiness(['name_en' => 'Sidra Coffee Roasters', 'name_ar' => 'محمصة سدرة للقهوة']);
        $this->publishedBusiness(['name_en' => 'Unrelated Garage', 'name_ar' => 'كراج', 'category_id' => Category::query()->where('slug', 'car-repair')->value('id')]);

        $this->assertSame(['Sidra Coffee Roasters'], collect($this->getJson('/api/v1/businesses?q=coffee', ['Accept-Language' => 'en'])->json('data'))->pluck('name')->all());
        $this->assertSame(1, $this->getJson('/api/v1/businesses?q=قهوه')->json('meta.total')); // Arabic taa-marbuta normalisation
        $this->getJson('/api/v1/businesses?sort=bogus')->assertStatus(422)->assertJsonPath('code', 'validation_failed');
    }

    public function test_localized_names_follow_accept_language(): void
    {
        $this->publishedBusiness(['name_ar' => 'مقهى الدوحة', 'name_en' => 'Doha Cafe']);

        $this->getJson('/api/v1/businesses', ['Accept-Language' => ''])->assertJsonPath('data.0.name', 'مقهى الدوحة');
        $this->getJson('/api/v1/businesses', ['Accept-Language' => 'ar-QA'])->assertJsonPath('data.0.name', 'مقهى الدوحة');
        $this->getJson('/api/v1/businesses', ['Accept-Language' => 'en'])->assertJsonPath('data.0.name', 'Doha Cafe')
            ->assertJsonPath('data.0.category.name', 'Cafés');
    }

    public function test_business_detail_hides_private_fields(): void
    {
        $owner = $this->owner();
        $business = $this->publishedBusiness();
        $business->forceFill(['owner_id' => $owner->id, 'moderation_notes' => 'internal note', 'rejection_reason' => 'old reason'])->save();

        $json = $this->getJson('/api/v1/businesses/'.$business->uuid)->assertOk()
            ->assertJsonPath('data.slug', $business->slug)
            ->assertJsonStructure(['data' => ['translations' => ['name' => ['ar', 'en']], 'contact', 'opening_hours', 'services', 'gallery']])
            ->json('data');

        foreach (['owner', 'owner_id', 'moderation_notes', 'rejection_reason', 'status', 'ip_hash', 'is_demo'] as $key) {
            $this->assertArrayNotHasKey($key, $json);
        }
        $this->assertStringNotContainsString($owner->email, json_encode($json));

        // Slugs work as identifiers too.
        $this->getJson('/api/v1/businesses/'.$business->slug)->assertOk()->assertJsonPath('data.id', $business->uuid);
    }

    public function test_unpublished_business_is_404_for_strangers_but_visible_to_owner(): void
    {
        $owner = $this->owner();
        $draft = Business::factory()->create(['owner_id' => $owner->id]);

        $this->getJson('/api/v1/businesses/'.$draft->uuid)->assertStatus(404)->assertJsonPath('code', 'not_found');
        $this->withToken($owner->createToken('t')->plainTextToken)->getJson('/api/v1/businesses/'.$draft->uuid)->assertOk();
    }

    public function test_categories_and_municipalities(): void
    {
        $this->publishedBusiness();

        $cats = $this->getJson('/api/v1/categories', ['Accept-Language' => 'en'])->assertOk()->json('data');
        $restaurants = collect($cats)->firstWhere('slug', 'restaurants-cafes');
        $this->assertSame(1, $restaurants['businesses_count']);
        $this->assertSame(1, collect($restaurants['children'])->firstWhere('slug', 'cafes')['businesses_count']);

        $this->getJson('/api/v1/municipalities')->assertOk()->assertJsonCount(8, 'data')
            ->assertJsonStructure(['data' => [['id', 'slug', 'name', 'areas' => [['id', 'slug', 'name']]]]]);
        $this->getJson('/api/v1/categories/unknown-slug')->assertStatus(404);
    }

    public function test_live_offers_exclude_expired_and_pending(): void
    {
        $business = $this->publishedBusiness();
        Offer::factory()->create(['business_id' => $business->id, 'title_en' => 'Live deal']);
        Offer::factory()->create(['business_id' => $business->id, 'title_en' => 'Expired deal', 'starts_at' => now()->subWeeks(2), 'ends_at' => now()->subDay()]);
        Offer::factory()->create(['business_id' => $business->id, 'title_en' => 'Pending deal', 'status' => 'pending']);

        $titles = collect($this->getJson('/api/v1/offers', ['Accept-Language' => 'en'])->assertOk()->json('data'))->pluck('title');
        $this->assertSame(['Live deal'], $titles->all());

        $expired = Offer::query()->where('title_en', 'Expired deal')->first();
        $this->getJson('/api/v1/offers/'.$expired->uuid)->assertStatus(404);
    }

    public function test_reviews_listing_reports_events_contact_and_settings(): void
    {
        $business = $this->publishedBusiness();

        $this->getJson("/api/v1/businesses/{$business->uuid}/reviews")->assertOk()->assertJsonPath('meta.rating.count', 0);
        $this->postJson("/api/v1/businesses/{$business->uuid}/reports", ['reason' => 'incorrect_info', 'details' => 'Wrong phone'])->assertCreated();
        $this->assertDatabaseHas('listing_reports', ['business_id' => $business->id, 'reason' => 'incorrect_info']);

        $this->postJson("/api/v1/businesses/{$business->uuid}/events", ['type' => 'call'], ['User-Agent' => 'DaleelApp/1.0'])->assertNoContent();
        $this->assertDatabaseHas('business_events', ['business_id' => $business->id, 'type' => 'call', 'source' => 'api']);
        $this->postJson("/api/v1/businesses/{$business->uuid}/events", ['type' => 'view'])->assertStatus(422);

        $this->postJson('/api/v1/contact', ['name' => 'A', 'email' => 'a@example.com', 'subject' => 'Hi', 'message' => 'Hello there team'])->assertCreated();
        $this->getJson('/api/v1/settings/app')->assertOk()->assertJsonPath('data.brand.name.en', 'Daleel Qatar')
            ->assertJsonPath('data.locales.default', 'ar');
        $this->get('/api/v1/openapi.yaml')->assertOk()->assertHeader('Content-Type', 'application/yaml; charset=UTF-8');
    }

    public function test_draft_status_constant_is_used_for_new_records(): void
    {
        $this->assertSame(BusinessStatus::Draft, Business::factory()->create()->status);
    }
}
