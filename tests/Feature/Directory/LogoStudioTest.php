<?php

namespace Tests\Feature\Directory;

use App\Models\AuditLog;
use App\Models\Business;
use App\Support\Discovery\UrlGuard;
use App\Support\Enrichment\LogoFinder;
use App\Support\Enrichment\WebsiteReader;
use App\Support\Permissions;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LogoStudioTest extends TestCase
{
    /** @var array<string, array{0: string, 1: string}> url => [body, content type] */
    private array $site = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['filesystems.media_disk' => 'public']);
        UrlGuard::resolveUsing(fn () => ['93.184.216.34']);
        $this->app->instance(LogoFinder::class, new LogoFinder(new WebsiteReader(0), 0));

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 80"><rect width="300" height="80" fill="#0a3d62"/><circle cx="40" cy="40" r="30" fill="#f6b93b"/><path d="M90 20h180v40H90z" fill="#fff"/></svg>';
        $this->site = [
            'https://alansari.qa' => ['<html><head><title>Al Ansari</title>'
                .'<script type="application/ld+json">{"@context":"https://schema.org","@type":"Organization","name":"Al Ansari","logo":{"@type":"ImageObject","url":"/media/brand-logo.png"}}</script>'
                .'<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png"><link rel="icon" sizes="32x32" href="/favicon-32.png">'
                .'<meta property="og:image" content="https://alansari.qa/media/share.jpg"></head><body>'
                .'<header><a class="site-logo" href="/"><img src="/media/header-logo.png" alt="Al Ansari"></a><a href="/" class="logo"><img src="/media/brand.svg" alt="Al Ansari logo"></a></header>'
                .'<main><h1>Al Ansari Furniture Centre</h1></main></body></html>', 'text/html; charset=utf-8'],
            'https://alansari.qa/media/brand-logo.png' => [$this->png(400, 120, transparent: true), 'image/png'],
            'https://alansari.qa/media/header-logo.png' => [$this->png(360, 100, transparent: true, light: true), 'image/png'],
            'https://alansari.qa/media/brand.svg' => [$svg, 'image/svg+xml'],
            'https://alansari.qa/apple-touch-icon.png' => [$this->png(180, 180), 'image/png'],
            'https://alansari.qa/favicon-32.png' => [$this->png(32, 32), 'image/png'],
            'https://alansari.qa/media/share.jpg' => [$this->png(1200, 300), 'image/png'],
        ];

        Http::fake(function (Request $request) {
            $url = $request->url();
            if (str_ends_with($url, '/robots.txt')) {
                return Http::response('', 404);
            }
            $key = rtrim(preg_replace('#^http://#', 'https://', $url), '/');
            if (isset($this->site[$key])) {
                return Http::response($this->site[$key][0], 200, ['Content-Type' => $this->site[$key][1]]);
            }

            return Http::response('', 404);
        });
    }

    protected function tearDown(): void
    {
        UrlGuard::resolveUsing(null);
        parent::tearDown();
    }

    public function test_it_finds_the_logo_on_the_website_behind_the_company_email(): void
    {
        $business = $this->business(['email' => 'contact@alansari.qa']);

        $response = $this->actingAs($this->makeUser(Permissions::ADMIN))
            ->postJson(route('admin.businesses.logo.find', $business->id))
            ->assertOk()
            ->assertJsonPath('sites.0.source', 'email')
            ->assertJsonPath('sites.0.reachable', true);

        $candidates = collect($response->json('candidates'));
        $this->assertSame('schema', $candidates->first()['source'], 'the structured-data logo is the most trusted');
        $this->assertSame(['schema', 'header', 'header', 'touch_icon'], $candidates->pluck('source')->take(4)->all());
        $this->assertContains('svg', $candidates->pluck('kind')->all());
        $this->assertNotContains('https://alansari.qa/favicon-32.png', $candidates->pluck('url')->all(), 'tiny favicons are skipped');
        $this->assertNotContains('https://alansari.qa/media/share.jpg', $candidates->pluck('url')->all(), 'wide sharing banners are not logos');
        $this->assertStringStartsWith('data:image/png;base64,', $candidates->first()['preview']);

        // The white header logo is placed on navy so it stays visible on white cards.
        $white = $candidates->firstWhere('url', 'https://alansari.qa/media/header-logo.png');
        $this->assertTrue($white['dark']);

        // A vector logo is given an explicit size so browsers can draw it.
        $svg = $candidates->firstWhere('kind', 'svg');
        $this->assertSame([300, 80], [$svg['width'], $svg['height']]);
        $this->assertStringContainsString('width="1200" height="320"', base64_decode(substr($svg['preview'], strlen('data:image/svg+xml;base64,'))));
        $this->assertStringContainsString(trans_choice('logos.found', $candidates->count(), ['count' => $candidates->count()]), $response->json('message'));
    }

    public function test_admin_applies_a_found_logo(): void
    {
        $business = $this->business(['website' => 'alansari.qa']);
        $admin = $this->makeUser(Permissions::ADMIN);
        $id = $this->actingAs($admin)->postJson(route('admin.businesses.logo.find', $business->id))->json('candidates.0.id');

        $this->actingAs($admin)->postJson(route('admin.businesses.logo.apply', $business->id), ['candidate' => $id, 'origin' => 'website'])
            ->assertOk()
            ->assertJsonPath('redirect', route('admin.businesses.show', $business->id));

        $path = $business->fresh()->logo_path;
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([LogoFinder::SIZE, LogoFinder::SIZE], [$w, $h]);

        $log = AuditLog::query()->where('action', 'business.logo.updated')->latest('id')->first();
        $this->assertSame('website', $log->metadata['origin']);
        $this->assertSame('https://alansari.qa/media/brand-logo.png', $log->metadata['source_url']);
        $this->assertSame(__('logos.saved'), session('success'));
    }

    public function test_admin_saves_a_branded_logo_or_a_drawn_vector_logo(): void
    {
        $business = $this->business();
        $admin = $this->makeUser(Permissions::ADMIN);

        $this->actingAs($admin)->post(route('admin.businesses.logo.apply', $business->id), [
            'logo' => UploadedFile::fake()->createWithContent('logo.png', $this->png(600, 600)),
            'origin' => 'generated',
            'style' => 'navy',
        ], ['Accept' => 'application/json'])->assertOk();
        $first = $business->fresh()->logo_path;
        Storage::disk('public')->assertExists($first);
        $this->assertSame('navy', AuditLog::query()->where('action', 'business.logo.updated')->latest('id')->first()->metadata['style']);

        // An SVG logo drawn by the browser is tidied like any website logo and replaces the old file.
        $this->actingAs($admin)->post(route('admin.businesses.logo.apply', $business->id), [
            'logo' => UploadedFile::fake()->createWithContent('logo.png', $this->png(1200, 320, transparent: true)),
            'origin' => 'website',
            'source_url' => 'https://alansari.qa/media/brand.svg',
        ], ['Accept' => 'application/json'])->assertOk();
        Storage::disk('public')->assertMissing($first);
        $this->assertSame('https://alansari.qa/media/brand.svg', AuditLog::query()->where('action', 'business.logo.updated')->latest('id')->first()->metadata['source_url']);

        // Only PNG files of a sensible size are accepted.
        $this->actingAs($admin)->post(route('admin.businesses.logo.apply', $business->id), [
            'logo' => UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml'),
            'origin' => 'generated',
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('logo');
        $this->actingAs($admin)->postJson(route('admin.businesses.logo.apply', $business->id), ['origin' => 'generated', 'style' => 'neon'])
            ->assertUnprocessable()->assertJsonValidationErrors(['logo', 'style']);
    }

    public function test_an_expired_or_foreign_candidate_is_refused(): void
    {
        $business = $this->business(['website' => 'alansari.qa']);
        $other = $this->business(['name_en' => 'Other Company']);
        $admin = $this->makeUser(Permissions::ADMIN);
        $id = $this->actingAs($admin)->postJson(route('admin.businesses.logo.find', $business->id))->json('candidates.0.id');

        $this->actingAs($admin)->postJson(route('admin.businesses.logo.apply', $other->id), ['candidate' => $id, 'origin' => 'website'])
            ->assertUnprocessable()->assertJsonPath('message', __('logos.expired'));
        $this->assertNull($other->fresh()->logo_path);
    }

    public function test_it_explains_when_there_is_no_website_to_look_at(): void
    {
        $business = $this->business(['email' => 'alansari.furniture@gmail.com']);
        $admin = $this->makeUser(Permissions::ADMIN);

        $this->actingAs($admin)->postJson(route('admin.businesses.logo.find', $business->id))
            ->assertOk()->assertJsonPath('sites', [])->assertJsonPath('candidates', [])->assertJsonPath('message', __('logos.no_website'));

        $this->actingAs($admin)->get(route('admin.businesses.show', $business->id))
            ->assertOk()->assertSee(__('logos.no_site_hint'))->assertSee(__('logos.design'))->assertSee('logoStudio(', false);

        $reachable = $this->business(['website' => 'https://down.example.qa']);
        $this->actingAs($admin)->postJson(route('admin.businesses.logo.find', $reachable->id))
            ->assertOk()->assertJsonPath('sites.0.reachable', false)->assertJsonPath('message', __('logos.unreachable'));
    }

    public function test_only_editors_can_use_the_logo_studio(): void
    {
        $business = $this->business(['website' => 'alansari.qa']);
        $user = $this->makeUser();

        $this->actingAs($user)->postJson(route('admin.businesses.logo.find', $business->id))->assertForbidden();
        $this->actingAs($user)->postJson(route('admin.businesses.logo.apply', $business->id), ['origin' => 'generated'])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_processing_trims_margins_and_keeps_a_square_frame(): void
    {
        $result = LogoFinder::process($this->png(800, 200, transparent: true));
        $image = imagecreatefromstring($result['png']);
        $this->assertSame([LogoFinder::SIZE, LogoFinder::SIZE], [imagesx($image), imagesy($image)]);
        $this->assertFalse($result['dark']);
        $this->assertSame(127, (imagecolorat($image, 2, 2) >> 24) & 0x7F, 'transparent logos keep a transparent background');

        $white = LogoFinder::process($this->png(400, 120, transparent: true, light: true));
        $this->assertTrue($white['dark']);
        $rgb = imagecolorat(imagecreatefromstring($white['png']), 2, 2) & 0xFFFFFF;
        $this->assertSame(0x101C32, $rgb, 'white artwork sits on the brand navy');

        $this->assertNull(LogoFinder::process('<?php echo 1;'));
    }

    private function business(array $attributes = []): Business
    {
        return Business::factory()->published()->create(array_merge([
            'name_en' => 'Al Ansari Furniture Centre',
            'name_ar' => 'مركز الأنصاري للأثاث',
            'website' => null,
            'email' => null,
            'is_demo' => false,
        ], $attributes));
    }

    /** A simple mark: a coloured bar with margins, optionally on transparency and in white. */
    private function png(int $w, int $h, bool $transparent = false, bool $light = false): string
    {
        $image = imagecreatetruecolor($w, $h);
        imagesavealpha($image, true);
        imagealphablending($image, false);
        imagefilledrectangle($image, 0, 0, $w - 1, $h - 1, $transparent ? imagecolorallocatealpha($image, 255, 255, 255, 127) : imagecolorallocate($image, 255, 255, 255));
        $ink = $light ? imagecolorallocate($image, 250, 250, 250) : imagecolorallocate($image, 10, 61, 98);
        imagefilledrectangle($image, (int) ($w * 0.15), (int) ($h * 0.25), (int) ($w * 0.85), (int) ($h * 0.75), $ink);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
