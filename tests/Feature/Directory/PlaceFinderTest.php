<?php

namespace Tests\Feature\Directory;

use App\Enums\BusinessStatus;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessSuggestion;
use App\Models\Category;
use App\Models\Municipality;
use App\Services\BusinessManager;
use App\Support\Permissions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlaceFinderTest extends TestCase
{
    /** @var list<array<string,mixed>> places the fake Google search returns */
    private array $search = [];

    /** @var array<string, array<string,mixed>> place id => English details */
    private array $details = [];

    private ?string $nextToken = null;

    private int $searchStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google_places.key' => 'test-google-key', 'services.anthropic.key' => null]);

        $this->search = [
            $this->place('ChIJ_Wakrah_Grill_00001', 'Wakrah Sea Grill', '+974 4464 1001', 25.1712, 51.6031),
            $this->place('ChIJ_Wakrah_Karak_00002', 'Karak House Al Wakrah', '+974 5550 2002', 25.1801, 51.6102, ['primaryTypeDisplayName' => ['text' => 'Cafe'], 'types' => ['cafe', 'food', 'point_of_interest']]),
            $this->place('ChIJ_Wakrah_Closed_0003', 'Old Harbour Diner', null, 25.1650, 51.5990, ['businessStatus' => 'CLOSED_PERMANENTLY']),
        ];
        foreach ($this->search as $place) {
            $this->details[$place['id']] = $place + [
                'regularOpeningHours' => [
                    'periods' => array_map(fn ($d) => ['open' => ['day' => $d, 'hour' => 12, 'minute' => 0], 'close' => ['day' => $d, 'hour' => 23, 'minute' => 0]], range(0, 6)),
                    'weekdayDescriptions' => ['Monday: 12:00 – 11:00 PM'],
                ],
            ];
        }

        Http::fake(function (Request $request) {
            $url = $request->url();
            if (str_contains($url, '/v1/places:searchText')) {
                if ($this->searchStatus !== 200) {
                    return Http::response(['error' => ['status' => 'PERMISSION_DENIED']], $this->searchStatus);
                }

                return Http::response(array_filter(['places' => $this->search, 'nextPageToken' => $this->nextToken]));
            }
            if (preg_match('#/v1/places/([A-Za-z0-9_\-]+)#', $url, $m)) {
                if (! isset($this->details[$m[1]])) {
                    return Http::response(['error' => ['status' => 'NOT_FOUND']], 404);
                }
                if (str_contains($url, 'languageCode=ar')) {
                    return Http::response(['displayName' => ['text' => 'مشويات الوكرة البحرية'], 'formattedAddress' => 'الوكرة، قطر']);
                }

                return Http::response($this->details[$m[1]]);
            }

            return Http::response('', 404);
        });
    }

    private function place(string $id, string $name, ?string $phone, float $lat, float $lng, array $extra = []): array
    {
        return array_replace([
            'id' => $id,
            'displayName' => ['text' => $name, 'languageCode' => 'en'],
            'formattedAddress' => 'Al Wakrah, Qatar',
            'location' => ['latitude' => $lat, 'longitude' => $lng],
            'googleMapsUri' => 'https://maps.google.com/?cid='.crc32($id),
            'internationalPhoneNumber' => $phone,
            'websiteUri' => null,
            'businessStatus' => 'OPERATIONAL',
            'primaryTypeDisplayName' => ['text' => 'Seafood restaurant'],
            'types' => ['seafood_restaurant', 'restaurant', 'food', 'point_of_interest', 'establishment'],
            'rating' => 4.6,
            'userRatingCount' => 312,
        ], $extra);
    }

    private function admin()
    {
        return $this->makeUser(Permissions::ADMIN);
    }

    private function wakrah(): Municipality
    {
        return Municipality::query()->where('slug', 'al-wakrah')->firstOrFail();
    }

    public function test_page_renders_for_admins_only_and_explains_a_missing_key(): void
    {
        $this->actingAs($this->admin())->get('/en/admin/places')->assertOk()
            ->assertSee('Google Maps finder')->assertSee('Search Google Maps')->assertDontSee('Google Maps is not connected');
        $this->actingAs($this->admin())->get('/admin/places')->assertOk()->assertSee('الباحث في خرائط Google');

        config(['services.google_places.key' => null]);
        $this->actingAs($this->admin())->get('/en/admin/places')->assertOk()->assertSee('Google Maps is not connected');

        $this->actingAs($this->makeUser(Permissions::MODERATOR))->get('/en/admin/places')->assertForbidden();
        $this->actingAs($this->makeUser(Permissions::MODERATOR))->postJson('/en/admin/places/search', ['keyword' => 'cafe', 'mode' => 'area'])->assertForbidden();
    }

    public function test_area_search_matches_places_against_the_directory(): void
    {
        $linked = Business::factory()->published()->create(['name_en' => 'Wakrah Sea Grill', 'google_place_id' => 'ChIJ_Wakrah_Grill_00001', 'is_demo' => false]);
        // Same phone number and a similar name, but not linked yet.
        $samePhone = Business::factory()->published()->create(['name_en' => 'Karak House', 'phone' => '+974 5550 2002', 'is_demo' => false, 'municipality_id' => $this->wakrah()->id]);
        $this->nextToken = 'next-page-token-1';

        $response = $this->actingAs($this->admin())->postJson('/en/admin/places/search', [
            'keyword' => 'restaurants', 'mode' => 'area', 'municipality_id' => $this->wakrah()->id,
        ])->assertOk();

        $response->assertJsonPath('query', 'restaurants in Al Wakrah, Qatar')->assertJsonPath('next', 'next-page-token-1');
        $rows = collect($response->json('rows'))->keyBy('place_id');
        $this->assertSame('linked', $rows['ChIJ_Wakrah_Grill_00001']['state']);
        $this->assertSame($linked->id, $rows['ChIJ_Wakrah_Grill_00001']['business']['id']);
        $this->assertContains($rows['ChIJ_Wakrah_Karak_00002']['state'], ['likely', 'possible']);
        $this->assertSame($samePhone->id, $rows['ChIJ_Wakrah_Karak_00002']['business']['id']);
        $this->assertContains('phone_match', $rows['ChIJ_Wakrah_Karak_00002']['reasons']);
        $this->assertSame('new', $rows['ChIJ_Wakrah_Closed_0003']['state']);
        $this->assertSame('CLOSED_PERMANENTLY', $rows['ChIJ_Wakrah_Closed_0003']['business_status']);
        // A category and a municipality are proposed for every place.
        $this->assertSame(Category::query()->where('slug', 'cafes')->value('id'), $rows['ChIJ_Wakrah_Karak_00002']['category_id']);
        $this->assertSame($this->wakrah()->id, $rows['ChIJ_Wakrah_Closed_0003']['municipality_id']);

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'places:searchText')
                && $request['textQuery'] === 'restaurants in Al Wakrah, Qatar'
                && $request['pageSize'] === 20
                && isset($request['locationRestriction']['rectangle'])
                && str_contains($request->header('X-Goog-FieldMask')[0], 'nextPageToken');
        });

        // The next page sends the token back.
        $this->actingAs($this->admin())->postJson('/en/admin/places/search', ['keyword' => 'restaurants', 'mode' => 'area', 'page_token' => 'next-page-token-1'])->assertOk();
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'places:searchText') && ($request['pageToken'] ?? null) === 'next-page-token-1');
    }

    public function test_point_search_limits_google_to_the_radius_and_validates_input(): void
    {
        $this->actingAs($this->admin())->postJson('/en/admin/places/search', ['keyword' => 'karak', 'mode' => 'point', 'lat' => 25.17, 'lng' => 51.60, 'radius' => 2])
            ->assertOk()->assertJsonPath('query', 'karak');

        Http::assertSent(function (Request $request) {
            $box = $request['locationRestriction']['rectangle'] ?? null;

            return $box !== null && abs($box['low']['latitude'] - (25.17 - 2 / 111.32)) < 0.0001 && abs($box['high']['latitude'] - (25.17 + 2 / 111.32)) < 0.0001;
        });

        $admin = $this->admin();
        $this->actingAs($admin)->postJson('/en/admin/places/search', ['keyword' => 'karak', 'mode' => 'point'])->assertUnprocessable()->assertJsonValidationErrors(['lat', 'lng', 'radius']);
        $this->actingAs($admin)->postJson('/en/admin/places/search', ['keyword' => 'karak', 'mode' => 'point', 'lat' => 40.0, 'lng' => 51.6, 'radius' => 2])->assertJsonValidationErrors(['lat']);
        $this->actingAs($admin)->postJson('/en/admin/places/search', ['keyword' => 'k', 'mode' => 'area'])->assertJsonValidationErrors(['keyword']);
        $this->actingAs($admin)->postJson('/en/admin/places/search', ['keyword' => 'karak', 'mode' => 'point', 'lat' => 25.1, 'lng' => 51.5, 'radius' => 7])->assertJsonValidationErrors(['radius']);
    }

    public function test_google_errors_are_explained(): void
    {
        $this->searchStatus = 403;
        $this->actingAs($this->admin())->postJson('/en/admin/places/search', ['keyword' => 'restaurants', 'mode' => 'area'])
            ->assertStatus(502)->assertJsonPath('message', 'Google refused the request. Check that the Places API (New) is enabled for the API key.');

        config(['services.google_places.key' => null]);
        $this->actingAs($this->admin())->postJson('/en/admin/places/search', ['keyword' => 'restaurants', 'mode' => 'area'])
            ->assertStatus(502)->assertJsonPath('message', 'Google Maps is not connected yet.');
    }

    public function test_adding_a_place_creates_a_linked_listing_with_what_the_admin_confirmed(): void
    {
        $admin = $this->admin();
        $category = Category::query()->where('slug', 'international-cuisine')->value('id');

        $response = $this->actingAs($admin)->postJson('/en/admin/places/add', [
            'place_id' => 'ChIJ_Wakrah_Grill_00001',
            'name_en' => 'Wakrah Sea Grill Restaurant',
            'category_id' => $category,
            'phone' => '4464 1001',
            'hours' => true,
            'publish' => true,
        ])->assertOk()->assertJsonPath('status', 'added');

        $business = Business::query()->where('google_place_id', 'ChIJ_Wakrah_Grill_00001')->firstOrFail();
        $this->assertSame($business->id, $response->json('business.id'));
        $this->assertSame('Wakrah Sea Grill Restaurant', $business->name_en);
        $this->assertSame('مشويات الوكرة البحرية', $business->name_ar);
        $this->assertSame($category, $business->category_id);
        $this->assertSame($this->wakrah()->id, $business->municipality_id);
        $this->assertSame('+974 4464 1001', $business->phone);
        $this->assertSame('الوكرة، قطر', $business->address_ar);
        $this->assertSame('exact', $business->geo_precision);
        $this->assertEqualsWithDelta(25.1712, $business->latitude, 0.00001);
        $this->assertSame(BusinessStatus::Approved, $business->status);
        $this->assertNotNull($business->published_at);
        $this->assertNull($business->enriched_at, 'the listing stays due for the website and AI check');
        $this->assertSame(7, $business->openingHours()->count());
        $this->assertTrue(AuditLog::query()->where('action', 'places.added')->where('auditable_id', $business->id)->exists());

        // The same place cannot be added twice.
        $this->actingAs($admin)->postJson('/en/admin/places/add', ['place_id' => 'ChIJ_Wakrah_Grill_00001'])
            ->assertUnprocessable()->assertJsonValidationErrors(['place_id']);

        // A search now shows it as listed.
        $rows = collect($this->actingAs($admin)->postJson('/en/admin/places/search', ['keyword' => 'restaurants', 'mode' => 'area'])->json('rows'))->keyBy('place_id');
        $this->assertSame('linked', $rows['ChIJ_Wakrah_Grill_00001']['state']);
    }

    public function test_adding_checks_the_values_and_can_save_a_draft(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson('/en/admin/places/add', ['place_id' => 'ChIJ_Wakrah_Karak_00002', 'name_ar' => 'Karak House'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name_ar']);
        $this->actingAs($admin)->postJson('/en/admin/places/add', ['place_id' => 'ChIJ_Wakrah_Karak_00002', 'phone' => 'call us'])
            ->assertUnprocessable()->assertJsonValidationErrors(['phone']);
        $this->actingAs($admin)->postJson('/en/admin/places/add', ['place_id' => 'ChIJ_Wakrah_Karak_00002', 'name_en' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors(['name_en']);
        $this->actingAs($admin)->postJson('/en/admin/places/add', ['place_id' => 'bad id!'])->assertJsonValidationErrors(['place_id']);
        $this->assertSame(0, Business::query()->whereNotNull('google_place_id')->count());

        $this->actingAs($admin)->postJson('/en/admin/places/add', ['place_id' => 'ChIJ_Wakrah_Karak_00002', 'name_ar' => 'بيت الكرك', 'hours' => false, 'publish' => false, 'website' => ''])->assertOk();
        $business = Business::query()->where('google_place_id', 'ChIJ_Wakrah_Karak_00002')->firstOrFail();
        $this->assertSame(BusinessStatus::Draft, $business->status);
        $this->assertSame('بيت الكرك', $business->name_ar);
        $this->assertSame(0, $business->openingHours()->count());
        $this->assertSame(Category::query()->where('slug', 'cafes')->value('id'), $business->category_id);

        $this->actingAs($admin)->postJson('/en/admin/places/add', ['place_id' => 'ChIJ_Missing_Place_0001'])->assertStatus(502)
            ->assertJsonPath('message', 'This place is no longer on Google Maps.');
    }

    public function test_a_likely_duplicate_is_flagged_and_can_still_be_added_on_purpose(): void
    {
        $existing = Business::factory()->published()->create(['name_en' => 'Karak House Al Wakrah', 'phone' => '+974 5550 2002', 'is_demo' => false]);

        $this->actingAs($this->admin())->postJson('/en/admin/places/add', ['place_id' => 'ChIJ_Wakrah_Karak_00002'])
            ->assertStatus(409)->assertJsonPath('status', 'duplicate')->assertJsonPath('business.id', $existing->id);
        $this->assertNull(Business::query()->where('google_place_id', 'ChIJ_Wakrah_Karak_00002')->first());

        $this->actingAs($this->admin())->postJson('/en/admin/places/add', ['place_id' => 'ChIJ_Wakrah_Karak_00002', 'force' => true])->assertOk();
        $this->assertNotNull(Business::query()->where('google_place_id', 'ChIJ_Wakrah_Karak_00002')->first());
    }

    public function test_bulk_add_reports_each_place(): void
    {
        Business::factory()->published()->create(['name_en' => 'Karak House Al Wakrah', 'phone' => '+974 5550 2002', 'is_demo' => false]);
        $cafes = Category::query()->where('slug', 'cafes')->value('id');

        $results = collect($this->actingAs($this->admin())->postJson('/en/admin/places/add-many', [
            'items' => [
                ['place_id' => 'ChIJ_Wakrah_Grill_00001', 'category_id' => $cafes],
                ['place_id' => 'ChIJ_Wakrah_Karak_00002'],
                ['place_id' => 'ChIJ_Missing_Place_0001'],
            ],
            'publish' => false,
        ])->assertOk()->json('results'))->keyBy('place_id');

        $this->assertSame('added', $results['ChIJ_Wakrah_Grill_00001']['status']);
        $this->assertSame('duplicate', $results['ChIJ_Wakrah_Karak_00002']['status']);
        $this->assertSame('failed', $results['ChIJ_Missing_Place_0001']['status']);
        $grill = Business::query()->where('google_place_id', 'ChIJ_Wakrah_Grill_00001')->firstOrFail();
        $this->assertSame($cafes, $grill->category_id);
        $this->assertSame(BusinessStatus::Draft, $grill->status);
        $this->assertSame(7, $grill->openingHours()->count());

        $this->actingAs($this->admin())->postJson('/en/admin/places/add-many', ['items' => []])->assertJsonValidationErrors(['items']);
        $this->actingAs($this->admin())->postJson('/en/admin/places/add-many', ['items' => [['place_id' => 'ChIJ_Wakrah_Grill_00001'], ['place_id' => 'ChIJ_Wakrah_Grill_00001']]])
            ->assertJsonValidationErrors(['items.0.place_id']);
    }

    public function test_compare_offers_what_google_adds_or_says_differently_for_review(): void
    {
        $business = Business::factory()->published()->create([
            'name_en' => 'Wakrah Sea Grill', 'name_ar' => '', 'phone' => '+974 4000 0000', 'website' => null,
            'address_en' => 'Al Wakrah', 'address_ar' => null, 'latitude' => null, 'longitude' => null, 'is_demo' => false,
        ]);
        $enrichedAt = now()->subMonth()->startOfSecond();
        $business->forceFill(['enriched_at' => $enrichedAt])->saveQuietly();
        // An AI suggestion already waiting for review is kept, Google's values win where both have one.
        BusinessSuggestion::query()->create(['business_id' => $business->id, 'status' => 'pending', 'fields' => [
            'name_ar' => ['value' => 'مشويات البحر', 'source' => 'ai', 'confidence' => 'medium'],
            'description_en' => ['value' => 'Seafood grill on the Wakrah seafront.', 'source' => 'ai', 'confidence' => 'medium'],
        ], 'sources' => ['ai']]);

        $response = $this->actingAs($this->admin())->postJson('/en/admin/places/review', ['business_id' => $business->id, 'place_id' => 'ChIJ_Wakrah_Grill_00001'])
            ->assertOk()->assertJsonPath('status', 'review');

        $suggestion = BusinessSuggestion::query()->where('business_id', $business->id)->pending()->sole();
        $this->assertSame(route('admin.enrichment.show', $suggestion), $response->json('url'));
        $fields = $suggestion->fields;
        $this->assertSame('ChIJ_Wakrah_Grill_00001', $fields['google_place']['value']['place_id']);
        $this->assertSame('مشويات الوكرة البحرية', $fields['name_ar']['value']);
        $this->assertSame('google', $fields['name_ar']['source']);
        $this->assertArrayHasKey('description_en', $fields);
        // The listing has another phone: offered, but not ticked by default.
        $this->assertSame('+974 4464 1001', $fields['phone']['value']);
        $this->assertSame('low', $fields['phone']['confidence']);
        $this->assertSame('google_phone_differs', $fields['phone']['note']);
        $this->assertSame('google_differs', $fields['address_en']['note']);
        $this->assertArrayHasKey('location', $fields);
        $this->assertArrayHasKey('opening_hours', $fields);
        $this->assertSame($enrichedAt->toIso8601String(), $business->fresh()->enriched_at->toIso8601String(), 'a comparison is not a full check');

        // Approving the link and the new phone updates the listing.
        $this->actingAs($this->admin())->post(route('en.admin.enrichment.approve', $suggestion), ['apply' => ['google_place' => 1, 'phone' => 1]])->assertRedirect();
        $business->refresh();
        $this->assertSame('ChIJ_Wakrah_Grill_00001', $business->google_place_id);
        $this->assertSame('+974 4464 1001', $business->phone);
    }

    public function test_compare_says_so_when_the_listing_already_matches(): void
    {
        $business = Business::factory()->published()->create([
            'name_en' => 'Old Harbour Diner', 'name_ar' => 'مشويات الوكرة البحرية', 'phone' => null, 'website' => null,
            'address_en' => 'Al Wakrah, Qatar', 'address_ar' => 'الوكرة، قطر', 'latitude' => 25.1650, 'longitude' => 51.5990, 'geo_precision' => 'exact',
            'google_place_id' => 'ChIJ_Wakrah_Closed_0003', 'is_demo' => false,
        ]);
        app(BusinessManager::class)->syncHours($business, array_fill(0, 7, ['periods' => [['opens' => '12:00', 'closes' => '23:00']]]));

        $this->actingAs($this->admin())->postJson('/en/admin/places/review', ['business_id' => $business->id, 'place_id' => 'ChIJ_Wakrah_Closed_0003'])
            ->assertOk()->assertJsonPath('status', 'same');

        // A listing linked to another place, or a place linked to another listing, is refused.
        $this->actingAs($this->admin())->postJson('/en/admin/places/review', ['business_id' => $business->id, 'place_id' => 'ChIJ_Wakrah_Grill_00001'])
            ->assertUnprocessable()->assertJsonPath('message', 'This listing is linked to another Google Maps place.');
        $other = Business::factory()->published()->create(['is_demo' => false]);
        $this->actingAs($this->admin())->postJson('/en/admin/places/review', ['business_id' => $other->id, 'place_id' => 'ChIJ_Wakrah_Closed_0003'])
            ->assertUnprocessable();
    }
}
