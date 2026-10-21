<?php

namespace Tests\Feature\Directory;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessSuggestion;
use App\Models\Category;
use App\Models\Municipality;
use App\Services\SettingsService;
use App\Support\Discovery\UrlGuard;
use App\Support\Enrichment\AiWriter;
use App\Support\Enrichment\Enricher;
use App\Support\Enrichment\WebsiteReader;
use App\Support\Permissions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EnrichmentTest extends TestCase
{
    private const PLACE = 'ChIJ_AlAnsariFurniture01';

    /** @var array<string, mixed> what the fake Google returns */
    private array $google = [];

    /** @var array<string, string> url => html of the fake company website */
    private array $site = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google_places.key' => 'test-google-key', 'services.anthropic.key' => null]);
        UrlGuard::resolveUsing(fn () => ['93.184.216.34']);
        $this->app->instance(WebsiteReader::class, new WebsiteReader(0));
        $this->app->instance(AiWriter::class, new FakeAiWriter);

        $this->google = [
            'search' => [$this->place()],
            'ar' => ['displayName' => ['text' => 'مركز الأنصاري للأثاث', 'languageCode' => 'ar'], 'formattedAddress' => 'نجمة، الدوحة، قطر'],
        ];
        $this->site = [
            'https://alansari.qa' => '<html><head><title>Al Ansari Furniture</title><meta name="description" content="Furniture showroom in Najma"></head><body>'
                .'<nav><a href="/about-us">About us</a><a href="/services">Our services</a></nav><main><h1>Al Ansari Furniture Centre</h1><p>Home and office furniture showroom in Najma, Doha since 1995.</p>'
                .'<a href="https://wa.me/97455501234">Chat on WhatsApp</a><a href="https://www.instagram.com/alansari.qa">Instagram</a></main></body></html>',
            'https://alansari.qa/about-us' => '<html><body><main><p>We design and supply majlis seating, bedrooms and office furniture for homes and companies across Qatar.</p></main></body></html>',
            'https://alansari.qa/services' => '<html><body><main><ul><li>Custom majlis seating</li><li>Office furniture</li><li>Curtains and upholstery</li></ul></main></body></html>',
        ];

        Http::fake(function (Request $request) {
            $url = $request->url();
            if (str_contains($url, 'places.googleapis.com/v1/places:searchText')) {
                return Http::response(['places' => $this->google['search']]);
            }
            if (str_contains($url, 'places.googleapis.com/v1/places/')) {
                if (($this->google['details_status'] ?? 200) !== 200) {
                    return Http::response(['error' => ['status' => 'NOT_FOUND']], $this->google['details_status']);
                }

                return str_contains($url, 'languageCode=ar')
                    ? Http::response($this->google['ar'])
                    : Http::response($this->google['en'] ?? $this->place() + ['reviews' => $this->reviews()]);
            }
            if (str_ends_with($url, '/robots.txt')) {
                return Http::response('', 404);
            }
            $key = rtrim(preg_replace('#^http://#', 'https://', $url), '/');
            if (isset($this->site[$key])) {
                return Http::response($this->site[$key], 200, ['Content-Type' => 'text/html; charset=utf-8']);
            }

            return Http::response('', 404);
        });
    }

    protected function tearDown(): void
    {
        UrlGuard::resolveUsing(null);
        parent::tearDown();
    }

    private function place(array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => self::PLACE,
            'displayName' => ['text' => 'Al Ansari Furniture Centre', 'languageCode' => 'en'],
            'formattedAddress' => 'Najma St, Doha, Qatar',
            'location' => ['latitude' => 25.2702, 'longitude' => 51.5462],
            'googleMapsUri' => 'https://maps.google.com/?cid=123456789',
            'nationalPhoneNumber' => '4436 4664',
            'internationalPhoneNumber' => '+974 4436 4664',
            'businessStatus' => 'OPERATIONAL',
            'rating' => 4.4,
            'userRatingCount' => 87,
            'regularOpeningHours' => [
                'periods' => array_merge(
                    array_map(fn ($d) => ['open' => ['day' => $d, 'hour' => 9, 'minute' => 0], 'close' => ['day' => $d, 'hour' => 21, 'minute' => 0]], [0, 1, 2, 3, 4, 6]),
                ),
                'weekdayDescriptions' => ['Monday: 9:00 AM – 9:00 PM', 'Friday: Closed'],
            ],
        ], $overrides);
    }

    private function reviews(): array
    {
        return [[
            'rating' => 5,
            'text' => ['text' => 'Great majlis quality and quick delivery.', 'languageCode' => 'en'],
            'originalText' => ['text' => 'Great majlis quality and quick delivery.', 'languageCode' => 'en'],
            'authorAttribution' => ['displayName' => 'Fatima A.', 'uri' => 'https://www.google.com/maps/contrib/1', 'photoUri' => 'https://lh3.googleusercontent.com/a/photo'],
            'relativePublishTimeDescription' => '2 months ago',
        ]];
    }

    private function business(array $attributes = []): Business
    {
        return Business::factory()->published()->create(array_merge([
            'name_en' => 'Al Ansari Furniture Centre',
            'name_ar' => '',
            'slug' => 'al-ansari-furniture-centre',
            'phone' => '+974 4436 4664',
            'whatsapp' => null,
            'email' => 'info@alansari.qa',
            'website' => null,
            'description_en' => null,
            'description_ar' => null,
            'tagline_en' => null,
            'tagline_ar' => null,
            'address_en' => 'Najma',
            'address_ar' => null,
            'latitude' => null,
            'longitude' => null,
            'category_id' => Category::query()->where('slug', 'furniture-home')->value('id'),
            'municipality_id' => Municipality::query()->where('slug', 'doha')->value('id'),
            'is_demo' => false,
        ], $attributes));
    }

    public function test_enrichment_collects_google_website_and_ai_suggestions(): void
    {
        $business = $this->business();

        $s = app(Enricher::class)->run($business);

        $this->assertSame('pending', $s->status);
        $this->assertGreaterThanOrEqual(80, $s->match_confidence);
        $this->assertContains('phone_match', $s->match['reasons']);
        $f = $s->fields;
        $this->assertSame('مركز الأنصاري للأثاث', $f['name_ar']['value']);
        $this->assertSame('google', $f['name_ar']['source']);
        $this->assertSame(self::PLACE, $f['google_place']['value']['place_id']);
        $this->assertSame(['lat' => 25.2702, 'lng' => 51.5462], $f['location']['value']);
        $this->assertSame('نجمة، الدوحة، قطر', $f['address_ar']['value']);
        // Website worked out from info@alansari.qa, then read for WhatsApp, social links and the profile.
        $this->assertSame('https://alansari.qa', $f['website']['value']);
        $this->assertSame('email', $f['website']['source']);
        $this->assertSame('+974 5550 1234', $f['whatsapp']['value']);
        $this->assertSame('high', $f['whatsapp']['confidence']);
        $this->assertSame(['instagram' => 'https://www.instagram.com/alansari.qa'], $f['social']['value']);
        $this->assertStringContainsString('majlis', $f['description_en']['value']);
        $this->assertSame('website', $f['description_ar']['source']);
        $this->assertCount(3, $f['services']['value']);
        $this->assertSame(['closed' => true], $f['opening_hours']['value']['days'][5]);
        $this->assertSame('inferred', $business->refresh()->website_status);
        $this->assertNotNull($business->enriched_at);
        // Nothing reaches the listing before approval.
        $this->assertSame('', (string) $business->name_ar);
        $this->assertNull($business->google_place_id);
    }

    public function test_free_mail_address_is_not_treated_as_a_website(): void
    {
        $this->google['search'] = [];
        $business = $this->business(['email' => 'alansari77@live.com']);

        $s = app(Enricher::class)->run($business);

        $this->assertSame('none', $business->refresh()->website_status);
        $this->assertSame('no_website', $s->notes['website']);
        $this->assertArrayNotHasKey('website', $s->fields);
        // No Google match and no Arabic name from Google: the AI suggests one instead.
        $this->assertSame('ai', $s->fields['name_ar']['source']);
        $this->assertSame('مركز الأنصاري للأثاث', $s->fields['name_ar']['value']);
    }

    public function test_weak_google_matches_are_not_used(): void
    {
        $this->google['search'] = [$this->place(['id' => 'ChIJ_OtherCompany0001', 'displayName' => ['text' => 'Gulf Steel Trading'], 'internationalPhoneNumber' => '+974 4000 0000', 'nationalPhoneNumber' => '4000 0000'])];
        $business = $this->business(['email' => null]);

        $s = app(Enricher::class)->run($business);

        $this->assertTrue($s->match['rejected']);
        $this->assertArrayNotHasKey('google_place', $s->fields);
        $this->assertArrayNotHasKey('location', $s->fields);
    }

    public function test_mobile_number_is_offered_as_unconfirmed_whatsapp(): void
    {
        $this->google['search'] = [];
        $s = app(Enricher::class)->run($this->business(['phone' => '+974 5512 3456', 'email' => null]));

        $this->assertSame('inferred', $s->fields['whatsapp']['source']);
        $this->assertSame('low', $s->fields['whatsapp']['confidence']);
        $this->assertSame('mobile_maybe_whatsapp', $s->fields['whatsapp']['note']);
    }

    public function test_admin_reviews_edits_and_approves(): void
    {
        $admin = $this->makeUser(Permissions::ADMIN);
        $business = $this->business();
        $s = app(Enricher::class)->run($business);

        $this->actingAs($admin)->get(route('admin.enrichment.show', $s))->assertOk()
            ->assertSee('مركز الأنصاري للأثاث')->assertSee(__('enrichment.notes.website_from_email', ['domain' => 'alansari.qa']), false);

        $this->actingAs($admin)->post(route('admin.enrichment.approve', $s), [
            'apply' => ['name_ar' => 1, 'google_place' => 1, 'location' => 1, 'whatsapp' => 1, 'website' => 1, 'services' => 1, 'opening_hours' => 1, 'description_en' => 1],
            'value' => [
                'name_ar' => 'مركز الأنصاري للمفروشات',
                'location' => '25.2702, 51.5462',
                'whatsapp' => '55501234',
                'website' => 'https://alansari.qa',
                'description_en' => 'Furniture showroom in Najma for majlis seating and office furniture.',
                'services' => [['name_en' => 'Custom majlis seating', 'name_ar' => 'مجالس حسب الطلب'], ['name_en' => '', 'name_ar' => '']],
            ],
        ])->assertRedirect(route('admin.enrichment.index'))->assertSessionHasNoErrors();

        $business->refresh();
        $this->assertSame('مركز الأنصاري للمفروشات', $business->name_ar);
        $this->assertSame(self::PLACE, $business->google_place_id);
        $this->assertSame('https://maps.google.com/?cid=123456789', $business->google_maps_url);
        $this->assertSame('+974 5550 1234', $business->whatsapp);
        $this->assertSame('https://alansari.qa', $business->website);
        $this->assertSame('found', $business->website_status);
        $this->assertSame('exact', $business->geo_precision);
        $this->assertEqualsWithDelta(25.2702, $business->latitude, 0.00001);
        $this->assertSame(['Custom majlis seating'], $business->services()->pluck('name_en')->all());
        $this->assertSame(7, $business->openingHours()->distinct('day_of_week')->count('day_of_week'));
        $this->assertSame('partial', $s->refresh()->status);
        $this->assertSame($admin->id, $s->reviewed_by);
        $this->assertTrue(AuditLog::query()->where('action', 'enrichment.applied')->exists());
    }

    public function test_invalid_edits_are_rejected(): void
    {
        $admin = $this->makeUser(Permissions::ADMIN);
        $s = app(Enricher::class)->run($this->business());

        $this->actingAs($admin)->post(route('admin.enrichment.approve', $s), ['apply' => ['name_ar' => 1], 'value' => ['name_ar' => 'Not Arabic']])
            ->assertSessionHasErrors('fields.name_ar');
        $this->actingAs($admin)->post(route('admin.enrichment.approve', $s), ['apply' => ['location' => 1], 'value' => ['location' => '48.85, 2.35']])
            ->assertSessionHasErrors('fields.location');
        $this->actingAs($admin)->post(route('admin.enrichment.approve', $s), [])->assertSessionHasErrors('apply');
        $this->assertSame('pending', $s->refresh()->status);
    }

    public function test_a_google_place_cannot_be_linked_to_two_listings(): void
    {
        $this->business(['slug' => 'existing-ansari', 'name_en' => 'Al Ansari Furniture', 'email' => null])->forceFill(['google_place_id' => self::PLACE])->save();
        $s = app(Enricher::class)->run($this->business(), [], self::PLACE);

        $this->actingAs($this->makeUser(Permissions::ADMIN))->post(route('admin.enrichment.approve', $s), ['apply' => ['google_place' => 1]])
            ->assertSessionHasErrors('fields.google_place');
    }

    public function test_reject_and_choose_another_place(): void
    {
        $admin = $this->makeUser(Permissions::ADMIN);
        $business = $this->business(['email' => null]);
        $s = app(Enricher::class)->run($business);

        $this->actingAs($admin)->post(route('admin.enrichment.place', $s), ['place_id' => self::PLACE])->assertRedirect();
        $this->assertSame('superseded', $s->refresh()->status);
        $new = BusinessSuggestion::query()->latest('id')->firstOrFail();
        $this->assertContains('chosen_by_admin', $new->match['reasons']);

        $this->actingAs($admin)->post(route('admin.enrichment.reject', $new))->assertRedirect();
        $this->assertSame('rejected', $new->refresh()->status);
    }

    public function test_bulk_runner_suggests_arabic_names_in_batches(): void
    {
        $admin = $this->makeUser(Permissions::ADMIN);
        $this->business();
        $this->business(['slug' => 'pearl-trading', 'name_en' => 'Pearl Trading W.L.L', 'email' => null]);

        $response = $this->actingAs($admin)->postJson(route('admin.enrichment.bulk'), ['scope' => 'missing_arabic', 'mode' => 'names'])->assertOk();

        $this->assertSame(0, $response->json('errors'), (string) json_encode($response->json()));
        $this->assertSame(2, $response->json('processed'));
        $this->assertTrue($response->json('done'));
        $this->assertSame(2, BusinessSuggestion::query()->pending()->count());
    }

    public function test_bulk_full_run_and_pages_render(): void
    {
        $admin = $this->makeUser(Permissions::ADMIN);
        $business = $this->business();

        $this->actingAs($admin)->postJson(route('admin.enrichment.bulk'), ['scope' => 'not_enriched', 'mode' => 'full'])
            ->assertOk()->assertJson(['processed' => 1, 'suggested' => 1, 'done' => true]);

        $this->actingAs($admin)->get(route('admin.enrichment.index'))->assertOk()->assertSee('Al Ansari Furniture Centre')->assertSee(__('enrichment.bulk.title'));
        $this->actingAs($admin)->get(route('admin.enrichment.index', ['tab' => 'no_website']))->assertOk();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee(__('enrichment.quality.title'));
        $this->actingAs($admin)->get(route('admin.businesses.show', $business->id))->assertOk()->assertSee(__('enrichment.panel.title'));
        $this->actingAs($admin)->post(route('admin.enrichment.business', $business->id))->assertRedirect();
    }

    public function test_moderators_cannot_use_enrichment(): void
    {
        $this->actingAs($this->makeUser(Permissions::MODERATOR))->get(route('admin.enrichment.index'))->assertForbidden();
    }

    public function test_google_reviews_are_served_live_with_attribution_and_not_stored(): void
    {
        $business = $this->business();
        $this->get('/en/business/al-ansari-furniture-centre/google.json')->assertNotFound();

        $business->forceFill(['google_place_id' => self::PLACE, 'google_maps_url' => 'https://maps.google.com/?cid=123456789'])->save();

        $this->get('/en/business/al-ansari-furniture-centre')->assertOk()
            ->assertSee('googleReviews(', false)
            ->assertSee(__('directory.google.view_on_maps', [], 'en'));
        $this->getJson('/en/business/al-ansari-furniture-centre/google.json')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex')
            ->assertJsonPath('rating', 4.4)
            ->assertJsonPath('count', 87)
            ->assertJsonPath('reviews.0.author', 'Fatima A.')
            ->assertJsonPath('reviews.0.rating', 5);

        // Google reviews never become the directory's own reviews.
        $this->assertSame(0, $business->reviews()->count());
        $this->assertNull($business->refresh()->rating_avg);

        app(SettingsService::class)->set('integrations.google_reviews', false);
        $this->getJson('/en/business/al-ansari-furniture-centre/google.json')->assertNotFound();
    }
}

/** Deterministic stand-in for the Claude writer. */
class FakeAiWriter implements AiWriter
{
    public function available(): bool
    {
        return true;
    }

    public function arabicNames(array $businesses): array
    {
        $known = ['Al Ansari Furniture Centre' => 'مركز الأنصاري للأثاث', 'Pearl Trading W.L.L' => 'اللؤلؤة للتجارة'];

        return collect($businesses)->mapWithKeys(fn ($b) => [$b['id'] => $known[$b['name']] ?? 'شركة'])->all();
    }

    public function profileFromWebsite(string $name, ?string $category, array $pages): array
    {
        return [
            'found' => str_contains(implode(' ', $pages), 'majlis'),
            'description_en' => 'Furniture showroom in Najma, Doha, supplying majlis seating, bedrooms and office furniture.',
            'description_ar' => 'معرض أثاث في نجمة بالدوحة يوفر المجالس وغرف النوم وأثاث المكاتب.',
            'tagline_en' => 'Home and office furniture since 1995',
            'tagline_ar' => 'أثاث المنازل والمكاتب منذ 1995',
            'services' => [
                ['name_en' => 'Custom majlis seating', 'name_ar' => 'مجالس حسب الطلب'],
                ['name_en' => 'Office furniture', 'name_ar' => 'أثاث مكتبي'],
                ['name_en' => 'Curtains and upholstery', 'name_ar' => 'ستائر وتنجيد'],
            ],
        ];
    }
}
