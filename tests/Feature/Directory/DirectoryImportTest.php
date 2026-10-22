<?php

namespace Tests\Feature\Directory;

use App\Models\Business;
use App\Models\BusinessSource;
use App\Models\BusinessSuggestion;
use App\Models\Category;
use App\Models\ScrapeSource;
use App\Notifications\StaffAlert;
use App\Support\Discovery\DirectoryLookup;
use App\Support\Discovery\DiscoveryRunner;
use App\Support\Discovery\Extractor;
use App\Support\Discovery\Fetcher;
use App\Support\Discovery\Presets;
use App\Support\Discovery\SourceAnalyzer;
use App\Support\Discovery\UrlGuard;
use App\Support\Enrichment\AiWriter;
use App\Support\Import\BusinessImporter;
use App\Support\Import\Placeholders;
use App\Support\Permissions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Other directories (QBCD…): reading their pages, crawling their lists and completing one listing on demand. */
class DirectoryImportTest extends TestCase
{
    private const QBCD = 'https://www.qatarcontact.com';

    private const LISTING = self::QBCD.'/listing/a-r-group-trading-building-materials';

    protected function setUp(): void
    {
        parent::setUp();
        UrlGuard::resolveUsing(fn () => ['93.184.216.34']);
        FakeAiWriter::$profiles = [];
        $this->app->instance(AiWriter::class, new FakeAiWriter);
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        UrlGuard::resolveUsing(null);
        parent::tearDown();
    }

    private function fixture(): string
    {
        return (string) file_get_contents(base_path('tests/Fixtures/directories/qbcd-listing.html'));
    }

    private function qbcd(array $settings = []): ScrapeSource
    {
        $preset = Presets::get('qbcd');
        $source = new ScrapeSource(array_merge($preset, ['settings' => array_merge($preset['settings'], ['delay_ms' => 0], $settings), 'is_enabled' => true]));
        $source->slug = 'qbcd';
        $source->save();

        return $source;
    }

    /** One numbered list page of QBCD: result cards and a pager. */
    private function listPage(array $companies, int $page, int $last): string
    {
        $cards = '';
        foreach ($companies as $slug => $title) {
            $cards .= '<div class="listing-box" data-lat="25.2854" data-long="51.5310" data-title="'.e($title).'" data-route="'.self::QBCD.'/listing/'.$slug.'">'
                .'<a href="'.self::QBCD.'/listing/'.$slug.'"><h5 class="title">'.e($title).'</h5></a><a href="'.self::QBCD.'/listing/'.$slug.'">View</a></div>';
        }
        $pager = '';
        foreach (array_unique([1, max(1, $page - 1), min($last, $page + 1), $last]) as $n) {
            $pager .= '<a href="'.self::QBCD.'/listings?page='.$n.'">'.$n.'</a>';
        }

        return '<html><body><header><a href="'.self::QBCD.'/about-us">About</a><a href="'.self::QBCD.'/listings">Listings</a></header>'.$cards.'<nav class="pagination">'.$pager.'</nav></body></html>';
    }

    private function detail(string $name, string $phone, string $email): string
    {
        return '<html><head><meta property="og:title" content="'.e($name).' | QBCD"></head><body>'
            .'<div class="info-box"><h4 class="title">'.e($name).'</h4><p>Category : TRADING</p>'
            .'<p class="address"><a href="tel:'.$phone.'">'.$phone.'</a></p><p class="address"><a href="mailto:'.$email.'">'.$email.'</a></p></div>'
            .'<div id="description" class="description-box"><h4>Description</h4><p>'.e($name).' supplies general trading goods to companies across Qatar.</p></div></body></html>';
    }

    // ── Reading a directory page ────────────────────────────────────────────────

    public function test_it_reads_the_company_on_a_qbcd_page_and_ignores_the_publisher_and_placeholders(): void
    {
        $result = Extractor::extract($this->fixture(), self::LISTING);
        $record = $result['record'];

        $this->assertSame('smart', $result['method']);
        $this->assertSame('A R Group Trading Building Materials W.L.L.', $record['name_en']);
        $this->assertSame(['BUILDING MATERIALS'], $record['categories']);
        $this->assertSame('+97444427245', $record['phone']);
        $this->assertSame('alrasafaqatar01@gmail.com', $record['email']);
        $this->assertSame([], $record['extra_phones'], 'The directory’s own number (header, “Contact Seller”) is not the company’s.');
        $this->assertSame([], $record['extra_emails']);
        $this->assertNull($record['website'], 'The publisher’s website in the sidebar is not the company’s.');
        $this->assertNull($record['latitude'], 'The pin every QBCD listing shares is not a location.');
        $this->assertNull($record['address'], '“Doha, Qatar” alone is not an address…');
        $this->assertSame('Doha', $record['city'], '…but the city is kept.');
        $this->assertStringEndsWith('/listing_thumbnail/hOH3vYT24XAM3JAaK1q8eGnO6BDTdS.webp', (string) $record['image_url']);
        $this->assertNull($record['logo_url'], 'The directory’s own logo is never taken as the company logo.');
    }

    public function test_placeholder_coordinates_and_generic_addresses_are_recognised(): void
    {
        $this->assertTrue(Placeholders::isCoordinate(25.2854, 51.5310));
        $this->assertTrue(Placeholders::isCoordinate('25.28545', '51.53102'));
        $this->assertFalse(Placeholders::isCoordinate(25.3712, 51.5512));
        $this->assertTrue(Placeholders::isGenericAddress('Doha, Ad Dawḩah, Qatar'));
        $this->assertTrue(Placeholders::isGenericAddress('الدوحة، قطر'));
        $this->assertFalse(Placeholders::isGenericAddress('Building 12, Street 340, Al Sadd, Doha'));
        $this->assertSame('Doha', Placeholders::cityOf('Doha - Qatar'));
    }

    // ── Importing from a directory ──────────────────────────────────────────────

    public function test_a_directory_listing_is_imported_without_its_wording(): void
    {
        $record = Extractor::extract($this->fixture(), self::LISTING)['record'];
        $result = app(BusinessImporter::class)->import($record, 'crawl:qbcd', ['publish' => false, 'directory' => true]);

        $business = Business::query()->findOrFail($result['business_id']);
        $this->assertSame('created', $result['status']);
        $this->assertNull($business->description_en, 'Another directory’s description is never published as written.');
        $this->assertSame('alrasafaqatar01@gmail.com', $business->email);
        $this->assertSame('building-materials', $business->category?->slug, 'Their text still helps choose the category.');

        $details = BusinessSource::query()->where('business_id', $business->id)->value('details');
        $details = is_string($details) ? json_decode($details, true) : (array) $details;
        $this->assertStringContainsString('high-quality building materials', $details['description']);
        $this->assertStringContainsString('listing_thumbnail', $details['image']);
        $this->assertSame(['BUILDING MATERIALS'], $details['categories']);
    }

    public function test_numbered_pages_are_walked_to_the_end_and_only_new_companies_are_created(): void
    {
        Notification::fake();
        $admin = $this->makeUser(Permissions::ADMIN);
        Business::factory()->published()->create(['name_en' => 'Alpha Trading', 'phone' => '+974 4400 0001', 'is_demo' => false]);
        $pages = [
            1 => ['alpha-trading' => 'Alpha Trading', 'beta-supplies' => 'Beta Supplies'],
            2 => ['gamma-foods' => 'Gamma Foods'],
            3 => ['delta-print' => 'Delta Print'],
        ];
        Http::fake(function (Request $request) use ($pages) {
            $url = $request->url();
            if (preg_match('/listings\?page=(\d+)$/', $url, $m)) {
                return Http::response($this->listPage($pages[(int) $m[1]] ?? [], (int) $m[1], 3), 200, ['Content-Type' => 'text/html']);
            }
            if (preg_match('#/listing/([a-z-]+)$#', $url, $m)) {
                $n = array_search($m[1], ['alpha-trading', 'beta-supplies', 'gamma-foods', 'delta-print'], true);
                $name = ['Alpha Trading', 'Beta Supplies', 'Gamma Foods', 'Delta Print'][$n];

                return Http::response($this->detail($name, '+9744400000'.($n + 1), $m[1].'@example.qa'), 200, ['Content-Type' => 'text/html']);
            }

            return Http::response('', 404);
        });

        $source = $this->qbcd(['max_pages_per_run' => 50]);
        $run = app(DiscoveryRunner::class)->run($source, 30.0);

        $this->assertSame('ok', $run['status']);
        $this->assertSame(4, $run['discovered'], 'All four companies on the three pages were found.');
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'page=4'), 'The pager shows no page after 3.');
        $this->assertSame(3, $run['created']);
        $this->assertSame(1, Business::query()->where('name_en', 'Alpha Trading')->count(), 'A company already listed is completed, not duplicated.');
        $this->assertNotNull($source->fresh()->last_discovered_at, 'The list was read to its end.');
        $this->assertNull(Business::query()->where('name_en', 'Gamma Foods')->value('description_en'));

        Notification::assertSentTo($admin, StaffAlert::class, fn (StaffAlert $n) => $n->kind === 'directory' && str_contains($n->message, '3'));

        // The report lists exactly the new companies.
        $this->actingAs($admin)->get(route('admin.discovery.new', $source))
            ->assertOk()->assertSee('Beta Supplies')->assertSee('Gamma Foods')->assertSee('Delta Print')->assertDontSee('Alpha Trading');
    }

    public function test_a_logo_shown_on_a_directory_is_adopted_for_a_listing_without_one(): void
    {
        $png = $this->png(320, 320);
        Http::fake([
            self::QBCD.'/listing/*' => Http::response('<html><body><div class="company"><img class="company-logo" src="'.self::QBCD.'/logos/nour.png" alt="Nour logo">'
                .'<h1 class="title">Nour Optics</h1><a href="tel:+97444001122">Call</a></div></body></html>', 200, ['Content-Type' => 'text/html']),
            self::QBCD.'/logos/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
            '*' => Http::response('', 404),
        ]);
        $source = $this->qbcd();
        $source->forceFill(['discovery' => 'urls', 'start_urls' => self::QBCD.'/listing/nour-optics', 'last_discovered_at' => null])->save();

        app(DiscoveryRunner::class)->run($source, 20.0);

        $business = Business::query()->where('name_en', 'Nour Optics')->firstOrFail();
        $this->assertNotNull($business->logo_path);
        Storage::disk('public')->assertExists($business->logo_path);
    }

    // ── Admin: sources ──────────────────────────────────────────────────────────

    public function test_the_qbcd_preset_and_page_templates_can_be_saved(): void
    {
        $admin = $this->makeUser(Permissions::ADMIN);
        $this->actingAs($admin)->get(route('admin.discovery.index'))->assertOk()->assertSee(__('discovery.presets.qbcd.title'));
        $this->actingAs($admin)->get(route('admin.discovery.create', ['preset' => 'qbcd']))->assertOk()->assertSee('listings?page={page}', false);

        $form = ['name' => 'QBCD', 'base_url' => self::QBCD, 'discovery' => 'pages', 'url_pattern' => '/listing/', 'delay_ms' => 2500, 'max_pages_per_run' => 10, 'rediscover_hours' => 168,
            'search_url' => self::QBCD.'/listings?name={query}'];
        $this->actingAs($admin)->post(route('admin.discovery.store'), $form + ['start_urls' => self::QBCD.'/listings'])->assertSessionHasErrors('start_urls');
        $this->actingAs($admin)->post(route('admin.discovery.store'), $form + ['start_urls' => 'https://elsewhere.qa/list?page={page}'])->assertSessionHasErrors('start_urls');
        $this->actingAs($admin)->post(route('admin.discovery.store'), $form + ['start_urls' => self::QBCD.'/listings?page={page}'])->assertSessionHasNoErrors();

        $source = ScrapeSource::query()->where('name', 'QBCD')->firstOrFail();
        $this->assertSame(self::QBCD.'/listings?page={page}', $source->start_urls);
        $this->assertSame(self::QBCD.'/listings?name={query}', $source->setting('search_url'));
        $this->actingAs($admin)->get(route('admin.discovery.index'))->assertDontSee(__('discovery.presets.qbcd.title'), 'Once added, the preset is not offered again.');
    }

    public function test_settings_are_detected_from_the_website_address(): void
    {
        Http::fake(function (Request $request) {
            $url = $request->url();

            return match (true) {
                str_ends_with($url, '/robots.txt'), str_contains($url, 'sitemap') => Http::response('<html><body>Instructions</body></html>', 200, ['Content-Type' => 'text/html']),
                $url === self::QBCD || $url === self::QBCD.'/' => Http::response('<html><body><a href="'.self::QBCD.'/listings">All listings</a><a href="'.self::QBCD.'/about-us">About</a></body></html>', 200, ['Content-Type' => 'text/html']),
                str_contains($url, '/listings') => Http::response($this->listPage(['first-company-wll' => 'First Company', 'second-company' => 'Second Company', 'third-co-123' => 'Third Co', 'fourth-shop' => 'Fourth Shop', 'fifth-store-qa' => 'Fifth Store'], 1, 412), 200, ['Content-Type' => 'text/html']),
                str_contains($url, '/listing/') => Http::response($this->detail('First Company', '+97444001100', 'info@first.qa'), 200, ['Content-Type' => 'text/html']),
                default => Http::response('', 404),
            };
        });

        $result = (new SourceAnalyzer(new Fetcher(0)))->analyze(self::QBCD);

        $this->assertSame('pages', $result['mode']);
        $this->assertSame([self::QBCD.'/listings?page={page}'], $result['start_urls']);
        $this->assertSame('/listing/', $result['url_pattern']);
        $this->assertSame(412, $result['last_page']);
        $this->assertSame('First Company', $result['sample']['fields']['name_en']);
    }

    // ── Admin: one listing, "Import from QBCD" ──────────────────────────────────

    public function test_admin_imports_a_listings_missing_details_from_qbcd(): void
    {
        $admin = $this->makeUser(Permissions::ADMIN);
        $other = Category::query()->where('slug', 'other-businesses')->value('id');
        $business = Business::factory()->published()->create([
            'name_en' => 'A R Group Trading Building Materials', 'phone' => null, 'email' => null, 'description_en' => null, 'description_ar' => null,
            'category_id' => $other, 'latitude' => null, 'longitude' => null, 'is_demo' => false,
        ]);
        $this->qbcd();
        Http::fake([
            self::QBCD.'/listings?name=*' => Http::response($this->listPage(['a-r-group-trading-building-materials' => 'A R Group Trading Building Materials W.L.L.', 'a-r-motors' => 'A R Motors'], 1, 1), 200, ['Content-Type' => 'text/html']),
            self::LISTING => Http::response($this->fixture(), 200, ['Content-Type' => 'text/html']),
            '*' => Http::response('', 404),
        ]);

        $this->actingAs($admin)->get(route('admin.businesses.show', $business->id))->assertOk()->assertSee(__('enrichment.lookup.title'));

        $response = $this->actingAs($admin)->postJson(route('admin.enrichment.directory', $business->id))->assertOk();
        $response->assertJsonPath('status', 'matched')->assertJsonPath('listing.url', self::LISTING);

        $suggestion = BusinessSuggestion::query()->where('business_id', $business->id)->latest('id')->firstOrFail();
        $fields = $suggestion->fields;
        $this->assertSame('+974 4442 7245', $fields['phone']['value']);
        $this->assertSame('alrasafaqatar01@gmail.com', $fields['email']['value']);
        $this->assertSame('building-materials', Category::query()->find($fields['category']['value']['id'])?->slug);
        $this->assertArrayNotHasKey('location', $fields, 'The shared placeholder pin is not offered.');
        $this->assertSame('Supplier of building materials and construction tools in Doha.', $fields['description_en']['value']);
        $this->assertSame('written_from_directory', $fields['description_en']['note']);
        $this->assertSame('directory', FakeAiWriter::$profiles[0]['origin'], 'The AI is told the text is someone else’s and must be rewritten.');
        $this->assertStringContainsString('listing_thumbnail', $suggestion->notes['directory']['image']);
        $this->assertNotSame($this->fixtureDescription(), $fields['description_en']['value']);

        $this->actingAs($admin)->get(route('admin.enrichment.show', $suggestion))->assertOk()
            ->assertSee(__('enrichment.directory.card_hint'))->assertSee('listing_thumbnail', false);

        $this->actingAs($admin)->post(route('admin.enrichment.approve', $suggestion), ['apply' => ['phone' => 1, 'email' => 1, 'category' => 1, 'description_en' => 1]])->assertRedirect();
        $business->refresh();
        $this->assertSame('+974 4442 7245', $business->phone);
        $this->assertSame('alrasafaqatar01@gmail.com', $business->email);
        $this->assertSame('building-materials', $business->category?->slug);
        $this->assertSame('Supplier of building materials and construction tools in Doha.', $business->description_en);
        $this->assertTrue(BusinessSource::query()->where('business_id', $business->id)->where('source', 'crawl:qbcd')->exists(), 'The listing is linked to its QBCD page, so the crawler will not import it again.');
    }

    public function test_an_unsure_match_offers_candidates_and_a_chosen_page_must_be_on_the_directory(): void
    {
        $admin = $this->makeUser(Permissions::ADMIN);
        $business = Business::factory()->published()->create(['name_en' => 'Al Noor Pharmacy', 'phone' => '+974 4499 0000', 'email' => null, 'is_demo' => false]);
        $this->qbcd();
        Http::fake([
            self::QBCD.'/listings?name=*' => Http::response($this->listPage(['al-noor-trading' => 'Al Noor Trading', 'noor-pharma-supplies' => 'Noor Pharma Supplies'], 1, 1), 200, ['Content-Type' => 'text/html']),
            self::QBCD.'/listing/al-noor-trading' => Http::response($this->detail('Al Noor Trading', '+97444110000', 'trading@alnoor.qa'), 200, ['Content-Type' => 'text/html']),
            self::QBCD.'/listing/noor-pharma-supplies' => Http::response($this->detail('Noor Pharma Supplies', '+97444220000', 'info@noorpharma.qa'), 200, ['Content-Type' => 'text/html']),
            '*' => Http::response('', 404),
        ]);

        $this->actingAs($admin)->postJson(route('admin.enrichment.directory', $business->id))
            ->assertOk()->assertJsonPath('status', 'candidates')->assertJsonCount(2, 'candidates');
        $this->assertSame(0, BusinessSuggestion::query()->where('business_id', $business->id)->count(), 'Nothing is suggested from an unsure match.');

        $this->actingAs($admin)->postJson(route('admin.enrichment.directory', $business->id), ['url' => 'https://elsewhere.qa/listing/x'])->assertStatus(422);

        $this->actingAs($admin)->postJson(route('admin.enrichment.directory', $business->id), ['url' => self::QBCD.'/listing/noor-pharma-supplies'])
            ->assertOk()->assertJsonPath('status', 'matched');
        $this->assertSame('info@noorpharma.qa', BusinessSuggestion::query()->where('business_id', $business->id)->latest('id')->first()->fields['email']['value']);
    }

    public function test_names_are_compared_without_their_legal_form(): void
    {
        $this->assertGreaterThanOrEqual(DirectoryLookup::SURE, DirectoryLookup::nameScore('A R Group Trading Building Materials', 'A R Group Trading Building Materials W.L.L.'));
        $this->assertLessThan(0.75, DirectoryLookup::nameScore('Al Noor Pharmacy', 'Al Noor Trading'));
        $business = new Business(['name_en' => 'Pearl Coffee House W.L.L']);
        $this->assertSame(['Pearl Coffee House', 'Pearl Coffee'], DirectoryLookup::queries($business));
    }

    public function test_directories_can_only_be_used_by_import_managers(): void
    {
        $business = Business::factory()->published()->create(['is_demo' => false]);
        $this->qbcd();
        $this->actingAs($this->makeUser(Permissions::MODERATOR))->postJson(route('admin.enrichment.directory', $business->id))->assertForbidden();
        $this->actingAs($this->makeUser(Permissions::MODERATOR))->postJson(route('admin.discovery.analyze'), ['url' => self::QBCD])->assertForbidden();
    }

    private function fixtureDescription(): string
    {
        return 'A R Group Trading Building Materials W.L.L. provides high-quality building materials, construction tools, and supplies in Qatar.';
    }

    private function png(int $w, int $h): string
    {
        $image = imagecreatetruecolor($w, $h);
        imagefilledrectangle($image, 0, 0, $w - 1, $h - 1, imagecolorallocate($image, 255, 255, 255));
        imagefilledellipse($image, intdiv($w, 2), intdiv($h, 2), intdiv($w, 2), intdiv($h, 2), imagecolorallocate($image, 20, 60, 140));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
