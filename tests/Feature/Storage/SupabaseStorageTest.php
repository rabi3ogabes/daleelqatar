<?php

namespace Tests\Feature\Storage;

use App\Services\MediaService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Verifies the Supabase Storage adapter speaks the Storage REST API correctly.
 * (The live project is exercised during deployment; here HTTP is faked.)
 */
class SupabaseStorageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'filesystems.disks.supabase.url' => 'https://demo-ref.supabase.co',
            'filesystems.disks.supabase.key' => 'service-role-secret',
            'filesystems.disks.supabase_private.url' => 'https://demo-ref.supabase.co',
            'filesystems.disks.supabase_private.key' => 'service-role-secret',
            'filesystems.media_disk' => 'supabase',
            'filesystems.private_media_disk' => 'supabase_private',
        ]);
        Storage::forgetDisk(['supabase', 'supabase_private']);
        Http::preventStrayRequests();
    }

    public function test_upload_sends_authenticated_upsert_request_and_returns_public_url(): void
    {
        Http::fake(['demo-ref.supabase.co/storage/v1/object/daleel-public/*' => Http::response(['Key' => 'ok'], 200)]);

        $stored = app(MediaService::class)->storeImage(UploadedFile::fake()->image('logo.jpg', 800, 600), 'businesses/abc/logo');

        $this->assertStringEndsWith('.webp', $stored['path']);
        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && str_starts_with($request->url(), 'https://demo-ref.supabase.co/storage/v1/object/daleel-public/businesses/abc/logo/')
                && $request->hasHeader('Authorization', 'Bearer service-role-secret')
                && $request->hasHeader('x-upsert', 'true')
                && $request->hasHeader('Content-Type', 'image/webp');
        });

        $this->assertSame(
            'https://demo-ref.supabase.co/storage/v1/object/public/daleel-public/'.$stored['path'],
            Storage::disk('supabase')->url($stored['path'])
        );
    }

    public function test_private_documents_use_short_lived_signed_urls(): void
    {
        Http::fake([
            'demo-ref.supabase.co/storage/v1/object/sign/*' => Http::response(['signedURL' => '/object/sign/daleel-private/docs/a.pdf?token=abc'], 200),
            'demo-ref.supabase.co/storage/v1/object/daleel-private/*' => Http::response([], 200),
        ]);

        [$disk, $path] = app(MediaService::class)->storePrivateDocument(UploadedFile::fake()->create('cr.pdf', 100, 'application/pdf'), 'verifications/x');
        $this->assertSame('supabase_private', $disk);

        $url = app(MediaService::class)->temporaryUrl($disk, $path);
        $this->assertSame('https://demo-ref.supabase.co/storage/v1/object/sign/daleel-private/docs/a.pdf?token=abc', $url);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/object/sign/daleel-private/') && $r['expiresIn'] >= 60);
    }

    public function test_delete_and_exists(): void
    {
        Http::fake([
            'demo-ref.supabase.co/storage/v1/object/list/daleel-public' => Http::response([], 200),
            'demo-ref.supabase.co/storage/v1/object/daleel-public' => Http::response([], 200),
            'demo-ref.supabase.co/storage/v1/object/daleel-public/missing.webp' => Http::response([], 404),
            'demo-ref.supabase.co/storage/v1/object/daleel-public/present.webp' => Http::response('', 200, ['Content-Length' => '10']),
        ]);

        $this->assertTrue(Storage::disk('supabase')->exists('present.webp'));
        $this->assertFalse(Storage::disk('supabase')->exists('missing.webp'));
        Storage::disk('supabase')->delete('present.webp');
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && $r['prefixes'] === ['present.webp']);
    }

    public function test_service_role_key_never_appears_in_html(): void
    {
        $this->get('/')->assertOk()->assertDontSee('service-role-secret');
    }
}
