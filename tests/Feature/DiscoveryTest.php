<?php

namespace Tests\Feature;

use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\BusinessSource;
use App\Models\ScrapeSource;
use App\Support\Discovery\DiscoveryException;
use App\Support\Discovery\DiscoveryRunner;
use App\Support\Discovery\Extractor;
use App\Support\Discovery\RobotsTxt;
use App\Support\Discovery\Selector;
use App\Support\Discovery\UrlGuard;
use App\Support\Permissions;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class DiscoveryTest extends TestCase
{
    private const SITE = 'https://directory.example.qa';

    protected function setUp(): void
    {
        parent::setUp();
        // No real DNS in tests: every test host resolves to a public documentation address.
        UrlGuard::resolveUsing(fn (string $host) => match ($host) {
            'internal.example.qa' => ['10.0.0.5'],
            default => ['93.184.216.34'],
        });
    }

    protected function tearDown(): void
    {
        UrlGuard::resolveUsing(null);
        parent::tearDown();
    }

    private function source(array $attributes = []): ScrapeSource
    {
        $source = new ScrapeSource(array_merge([
            'name' => 'Example Directory',
            'base_url' => self::SITE,
            'discovery' => 'sitemap',
            'start_urls' => '',
            'settings' => ['delay_ms' => 0, 'max_pages_per_run' => 20],
            'is_enabled' => true,
        ], $attributes));
        $source->slug = 'example-directory';
        $source->save();

        return $source;
    }

    private function companyPage(string $name = 'Pearl Coffee Roasters', string $phone = '+974 4412 3456'): string
    {
        $ld = json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                ['@type' => 'Organization', 'name' => 'Example Directory', 'url' => self::SITE.'/', 'telephone' => '+974 4000 0000'],
                ['@type' => 'BreadcrumbList', 'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home'],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Cafes'],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $name],
                ]],
                [
                    '@type' => 'CafeOrCoffeeShop',
                    'name' => $name,
                    'alternateName' => 'قهوة اللؤلؤة',
                    'telephone' => $phone,
                    'email' => 'mailto:hello@pearlcoffee.qa',
                    'url' => 'https://pearlcoffee.qa',
                    'sameAs' => ['https://instagram.com/pearlcoffee'],
                    'address' => ['@type' => 'PostalAddress', 'streetAddress' => 'Porto Arabia, The Pearl', 'addressLocality' => 'Doha'],
                    'geo' => ['@type' => 'GeoCoordinates', 'latitude' => 25.3712, 'longitude' => 51.5512],
                    'aggregateRating' => ['@type' => 'AggregateRating', 'ratingValue' => 4.9, 'reviewCount' => 812],
                    'description' => 'Specialty coffee roastery and cafe serving single-origin beans at The Pearl.',
                ],
            ],
        ]);

        return '<!doctype html><html><head><title>'.$name.' | Example Directory</title><link rel="canonical" href="'.self::SITE.'/company/'.Str::slug($name).'">'
            .'<script type="application/ld+json">'.$ld.'</script></head><body><h1>'.$name.'</h1>'
            .'<footer><a href="tel:+97440000000">Call the directory</a></footer></body></html>';
    }

    public function test_sitemap_discovery_imports_a_pending_company_without_ratings(): void
    {
        Http::fake([
            self::SITE.'/robots.txt' => Http::response("User-agent: *\nDisallow: /private/\nSitemap: ".self::SITE."/sitemap.xml\n"),
            self::SITE.'/sitemap.xml' => Http::response('<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
                .'<url><loc>'.self::SITE.'/company/pearl-coffee</loc></url>'
                .'<url><loc>'.self::SITE.'/private/secret-company</loc></url>'
                .'<url><loc>https://elsewhere.example.com/company/x</loc></url></urlset>', 200, ['Content-Type' => 'application/xml']),
            self::SITE.'/company/pearl-coffee' => Http::response($this->companyPage(), 200, ['Content-Type' => 'text/html; charset=utf-8']),
            self::SITE.'/private/*' => Http::response('should never be requested', 500),
        ]);

        $source = $this->source();
        $result = app(DiscoveryRunner::class)->run($source, 15);

        $this->assertSame('ok', $result['status']);
        $this->assertSame(2, $result['discovered'], 'Off-site URLs are never queued.');
        $this->assertSame(1, $result['created']);

        $business = Business::query()->where('name_en', 'Pearl Coffee Roasters')->firstOrFail();
        $this->assertSame(BusinessStatus::Pending, $business->status);
        $this->assertSame('قهوة اللؤلؤة', $business->name_ar);
        $this->assertSame('+974 4412 3456', $business->phone);
        $this->assertSame('hello@pearlcoffee.qa', $business->email);
        $this->assertSame('https://pearlcoffee.qa', $business->website);
        $this->assertEqualsWithDelta(25.3712, (float) $business->latitude, 0.0001);
        $this->assertFalse((bool) $business->is_verified);
        $this->assertEquals(0, (float) ($business->rating_avg ?? 0), 'Ratings from other websites are never imported.');
        $this->assertTrue(BusinessSource::query()->where('business_id', $business->id)->where('source', 'crawl:example-directory')->exists());

        // robots.txt: the disallowed page is skipped without being requested.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/private/'));
        $this->assertDatabaseHas('scrape_urls', ['url' => self::SITE.'/private/secret-company', 'status' => 'skipped', 'message' => 'robots_disallowed']);

        // A second run finds nothing new and creates no duplicates.
        $source->refresh()->forceFill(['last_discovered_at' => null])->save();
        app(DiscoveryRunner::class)->run($source->refresh(), 15);
        $this->assertSame(1, Business::query()->where('name_en', 'Pearl Coffee Roasters')->count());
        $this->assertSame(1, (int) $source->refresh()->stats['created']);
    }

    public function test_a_company_found_on_two_sites_is_merged_not_duplicated(): void
    {
        Http::fake([
            self::SITE.'/company/pearl-coffee' => Http::response($this->companyPage(), 200, ['Content-Type' => 'text/html']),
            self::SITE.'/company/pearl-coffee-2' => Http::response($this->companyPage('Pearl Coffee Roasters WLL'), 200, ['Content-Type' => 'text/html']),
            self::SITE.'/robots.txt' => Http::response('', 404),
        ]);

        $source = $this->source(['discovery' => 'urls', 'start_urls' => self::SITE."/company/pearl-coffee\n".self::SITE.'/company/pearl-coffee-2']);
        $result = app(DiscoveryRunner::class)->run($source, 15);

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, Business::query()->where('phone', '+974 4412 3456')->count());
    }

    public function test_robots_txt_rules(): void
    {
        $robots = RobotsTxt::parse("User-agent: *\nDisallow: /\n\nUser-agent: DaleelQatarBot\nAllow: /company/\nDisallow: /company/*/edit$\nDisallow: /search\nCrawl-delay: 3\nSitemap: https://x.qa/s.xml", 'DaleelQatarBot');

        $this->assertTrue($robots->allows('https://x.qa/company/abc'));
        $this->assertFalse($robots->allows('https://x.qa/company/abc/edit'));
        $this->assertTrue($robots->allows('https://x.qa/company/abc/edit/more'));
        $this->assertFalse($robots->allows('https://x.qa/search?q=1'));
        $this->assertTrue($robots->allows('https://x.qa/about'), 'Our own group replaces the "*" group.');
        $this->assertSame(3.0, $robots->crawlDelay);
        $this->assertSame(['https://x.qa/s.xml'], $robots->sitemaps);

        $this->assertFalse(RobotsTxt::parse("User-agent: *\nDisallow: /", 'DaleelQatarBot')->allows('https://x.qa/anything'));
        $this->assertFalse(RobotsTxt::disallowAll()->allows('https://x.qa/'));
    }

    public function test_an_unreachable_robots_txt_blocks_crawling(): void
    {
        Http::fake([self::SITE.'/robots.txt' => Http::response('', 503), '*' => Http::response($this->companyPage())]);

        $this->expectException(DiscoveryException::class);
        $this->expectExceptionMessage('robots_disallowed');
        app(DiscoveryRunner::class)->test(self::SITE.'/company/pearl-coffee');
    }

    public function test_private_and_non_http_addresses_are_refused(): void
    {
        foreach (['http://127.0.0.1/', 'http://10.1.2.3/admin', 'http://169.254.169.254/latest/meta-data', 'http://[::1]/', 'http://localhost/', 'https://internal.example.qa/', 'ftp://directory.example.qa/', 'file:///etc/passwd', 'http://user:pass@directory.example.qa/', 'http://directory.example.qa:22/'] as $url) {
            try {
                UrlGuard::check($url);
                $this->fail("$url should be refused");
            } catch (DiscoveryException $e) {
                $this->assertContains($e->getMessage(), ['private_address', 'url_not_allowed'], $url);
            }
        }

        $this->assertSame('93.184.216.34', UrlGuard::check(self::SITE.'/company/x'));
        Http::fake();
        $this->expectException(DiscoveryException::class);
        app(DiscoveryRunner::class)->test('http://10.0.0.1/');
    }

    public function test_selectors_override_and_complete_structured_data(): void
    {
        $html = '<html><body><div class="card main"><h2 id="title">Doha Auto Care</h2><p class="addr">Street 33, Industrial Area</p>'
            .'<a class="tel" href="tel:+97455123456">Call</a><a class="tel" href="tel:44556677">Office</a><a href="mailto:info@dohaautocare.qa?subject=hi">Mail</a></div></body></html>';

        $result = Extractor::extract($html, self::SITE.'/c/1', ['name' => '.card #title', 'address' => 'div.main p.addr', 'phone' => 'a.tel@href', 'email' => 'a[href^=mailto]@href']);

        $this->assertSame('selectors', $result['method']);
        $this->assertSame('Doha Auto Care', $result['record']['name_en']);
        $this->assertSame('+97455123456', $result['record']['phone']);
        $this->assertSame('+97455123456', $result['record']['whatsapp']);
        $this->assertSame(['+97444556677'], $result['record']['extra_phones']);
        $this->assertSame('info@dohaautocare.qa', $result['record']['email']);
        $this->assertSame('Street 33, Industrial Area', $result['record']['address']);

        // Pages without company data (articles, listing pages) are never turned into companies.
        $this->assertNull(Extractor::extract('<html><head><meta property="og:title" content="Top 10 cafes"></head><body><h1>Top 10 cafes</h1></body></html>', self::SITE.'/blog/top')['record']);

        $dom = new DOMDocument;
        $dom->loadHTML('<div><ul class="x"><li data-k="a">One</li><li>Two</li></ul></div>');
        $this->assertSame(['One'], Selector::values(new DOMXPath($dom), 'ul.x > li[data-k=a]'));
        $this->assertSame(['a'], Selector::values(new DOMXPath($dom), 'li[data-k]@data-k'));
    }

    public function test_admin_can_manage_sources_and_test_a_page(): void
    {
        $admin = $this->makeUser(Permissions::ADMIN);
        $this->actingAs($admin);

        foreach (['', '/en'] as $prefix) {
            $this->get($prefix.'/admin/discovery')->assertOk();
            $this->get($prefix.'/admin/discovery/create')->assertOk();
        }

        $this->post('/admin/discovery', [
            'name' => 'Example Directory', 'base_url' => self::SITE, 'discovery' => 'links', 'start_urls' => 'https://elsewhere.example.com/list',
            'url_pattern' => '/company/', 'delay_ms' => 1500, 'max_pages_per_run' => 40, 'rediscover_hours' => 24,
        ])->assertSessionHasErrors('start_urls');

        $this->post('/admin/discovery', [
            'name' => 'Example Directory', 'base_url' => 'http://10.0.0.8', 'discovery' => 'sitemap', 'delay_ms' => 1500, 'max_pages_per_run' => 40, 'rediscover_hours' => 24,
        ])->assertSessionHasErrors('base_url');

        $this->post('/admin/discovery', [
            'name' => 'Example Directory', 'base_url' => self::SITE, 'discovery' => 'links', 'start_urls' => self::SITE.'/cafes',
            'url_pattern' => '/company/[^/]+$', 'selectors' => "phone: a.tel@href\nbogus: .x", 'delay_ms' => 1500, 'max_pages_per_run' => 40,
            'rediscover_hours' => 24, 'is_enabled' => '1',
        ])->assertRedirect(route('admin.discovery.index'));

        $source = ScrapeSource::query()->firstOrFail();
        $this->assertSame('example-directory', $source->slug);
        $this->assertSame(['phone' => 'a.tel@href'], $source->selectors);
        $this->assertFalse((bool) $source->setting('publish'));

        $this->get('/admin/discovery')->assertOk()->assertSee('Example Directory');
        $this->get('/en/admin/discovery/'.$source->id.'/edit')->assertOk()->assertSee('a.tel@href');

        Http::fake([
            self::SITE.'/robots.txt' => Http::response('', 404),
            self::SITE.'/company/pearl-coffee' => Http::response($this->companyPage(), 200, ['Content-Type' => 'text/html']),
        ]);
        $this->postJson('/admin/discovery/test', ['url' => self::SITE.'/company/pearl-coffee'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('fields.name_en', 'Pearl Coffee Roasters')
            ->assertJsonPath('fields.categories', 'Cafes');
        $this->postJson('/admin/discovery/test', ['url' => 'http://127.0.0.1/'])->assertStatus(422);

        $this->delete('/admin/discovery/'.$source->id)->assertRedirect(route('admin.discovery.index'));
        $this->assertDatabaseCount('scrape_sources', 0);
    }

    public function test_moderators_cannot_manage_discovery(): void
    {
        $this->actingAs($this->makeUser(Permissions::MODERATOR))->get('/admin/discovery')->assertForbidden();
    }

    public function test_cron_endpoint_requires_the_secret_and_runs_enabled_sources(): void
    {
        config(['security.cron_secret' => null]);
        $this->get('/cron/discovery')->assertNotFound();

        config(['security.cron_secret' => 'cron-test-secret']);
        $this->get('/cron/discovery')->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer wrong')->get('/cron/discovery')->assertUnauthorized();

        Http::fake([
            self::SITE.'/robots.txt' => Http::response('', 404),
            self::SITE.'/company/pearl-coffee' => Http::response($this->companyPage(), 200, ['Content-Type' => 'text/html']),
        ]);
        $this->source(['discovery' => 'urls', 'start_urls' => self::SITE.'/company/pearl-coffee']);

        $this->withHeader('Authorization', 'Bearer cron-test-secret')->get('/cron/discovery')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('sources.example-directory.created', 1);
    }
}
