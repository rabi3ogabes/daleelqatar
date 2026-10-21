<?php

namespace Tests\Feature\Account;

use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\Category;
use App\Models\Municipality;
use App\Models\User;
use App\Notifications\ListingSubmitted;
use App\Services\SettingsService;
use App\Support\Permissions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BusinessManagementTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        $category = Category::query()->where('slug', 'cafes')->firstOrFail();
        $municipality = Municipality::query()->where('slug', 'doha')->firstOrFail();

        return array_merge([
            'name_ar' => 'مقهى اللؤلؤة',
            'name_en' => 'Pearl Coffee',
            'category_id' => $category->id,
            'municipality_id' => $municipality->id,
            'area_id' => $municipality->areas()->first()->id,
            'description_ar' => 'مقهى مختص في قلب الدوحة.',
            'description_en' => 'Specialty coffee in the heart of Doha.',
            'phone' => '44001122',
            'website' => 'pearl-coffee.qa',
            'social' => ['instagram' => 'https://instagram.com/pearlcoffee'],
            'hours' => [
                '_present' => '1',
                '6' => ['enabled' => '1', 'periods' => [['opens' => '07:00', 'closes' => '23:00']]],
                '5' => ['enabled' => '1', 'closed' => '1'],
            ],
            'services' => [['name_ar' => 'قهوة', 'name_en' => 'Coffee', 'price_from' => '18']],
        ], $overrides);
    }

    private function ownedBusiness(User $owner, array $attributes = []): Business
    {
        return Business::factory()->create(array_merge(['owner_id' => $owner->id, 'is_demo' => false], $attributes));
    }

    public function test_owner_creates_a_draft_listing_with_details(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->post('/en/account/businesses', $this->payload());

        $business = Business::query()->where('name_en', 'Pearl Coffee')->firstOrFail();
        $response->assertRedirect(route('en.account.businesses.show', $business->uuid));
        $this->assertSame(BusinessStatus::Draft, $business->status);
        $this->assertSame($user->id, $business->owner_id);
        $this->assertSame('+97444001122', $business->phone);
        $this->assertSame('https://pearl-coffee.qa', $business->website);
        $this->assertCount(2, $business->openingHours);
        $this->assertSame(1, $business->services()->count());
        $this->assertSame(1, $business->socialLinks()->count());
        $this->assertTrue($user->fresh()->hasRole(Permissions::BUSINESS_OWNER));
    }

    public function test_validation_rejects_invalid_social_links_and_area_mismatch(): void
    {
        $user = $this->makeUser();
        $otherArea = Municipality::query()->where('slug', 'al-wakrah')->firstOrFail()->areas()->first();

        $this->actingAs($user)->post('/account/businesses', $this->payload([
            'social' => ['instagram' => 'https://evil.example.com/x'],
            'area_id' => $otherArea->id,
        ]))->assertSessionHasErrors(['social.instagram', 'area_id']);
    }

    public function test_owner_can_submit_complete_listing_for_review(): void
    {
        Notification::fake();
        $admin = $this->makeUser(Permissions::ADMIN);
        $user = $this->makeUser();
        $this->actingAs($user)->post('/account/businesses', $this->payload());
        $business = Business::query()->latest('id')->firstOrFail();

        $this->actingAs($user)->post('/account/businesses/'.$business->uuid.'/submit')
            ->assertRedirect(route('ar.account.businesses.show', $business->uuid))->assertSessionHas('success');

        $this->assertSame(BusinessStatus::Pending, $business->fresh()->status);
        $this->assertDatabaseHas('business_status_changes', ['business_id' => $business->id, 'to_status' => 'pending']);
        Notification::assertSentTo($admin, ListingSubmitted::class);
        // Pending listings are visible to the owner only, never to the public.
        $this->get('/business/'.$business->slug)->assertOk();
        auth()->guard('web')->logout();
        $this->get('/business/'.$business->slug)->assertNotFound();
    }

    public function test_incomplete_listing_cannot_be_submitted(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->post('/account/businesses', $this->payload(['description_ar' => null, 'phone' => null, 'website' => null]));
        $business = Business::query()->latest('id')->firstOrFail();

        $this->actingAs($user)->from('/account/businesses/'.$business->uuid)
            ->post('/account/businesses/'.$business->uuid.'/submit')->assertSessionHas('error');

        $this->assertSame(BusinessStatus::Draft, $business->fresh()->status);
    }

    public function test_owner_cannot_access_another_owners_listing(): void
    {
        $owner = $this->makeUser(Permissions::BUSINESS_OWNER);
        $intruder = $this->makeUser(Permissions::BUSINESS_OWNER);
        $business = $this->ownedBusiness($owner);
        $base = '/account/businesses/'.$business->uuid;

        $this->actingAs($intruder)->get($base)->assertNotFound();
        $this->actingAs($intruder)->get($base.'/edit')->assertNotFound();
        $this->actingAs($intruder)->put($base, $this->payload(['name_en' => 'Hijacked']))->assertNotFound();
        $this->actingAs($intruder)->delete($base)->assertNotFound();
        $this->actingAs($intruder)->post($base.'/submit')->assertNotFound();
        $this->actingAs($intruder)->post($base.'/logo', ['logo' => UploadedFile::fake()->image('x.png', 400, 400)])->assertNotFound();

        $this->assertNotSame('Hijacked', $business->fresh()->name_en);
        $this->assertNotNull($business->fresh());
    }

    public function test_owner_can_view_and_edit_own_listing(): void
    {
        $owner = $this->makeUser(Permissions::BUSINESS_OWNER);
        $business = $this->ownedBusiness($owner);

        $this->actingAs($owner)->get('/account/businesses/'.$business->uuid)->assertOk()->assertSee($business->name_ar);
        $this->actingAs($owner)->get('/en/account/businesses/'.$business->uuid.'/edit')->assertOk()->assertSee('Opening hours');
        $this->actingAs($owner)->put('/account/businesses/'.$business->uuid, $this->payload(['name_en' => 'Renamed Café']))
            ->assertRedirect(route('ar.account.businesses.edit', $business->uuid));
        $this->assertSame('Renamed Café', $business->fresh()->name_en);
        $this->assertDatabaseHas('audit_logs', ['action' => 'business.updated', 'auditable_id' => $business->id]);
    }

    public function test_suspended_listing_cannot_be_edited_by_owner(): void
    {
        $owner = $this->makeUser(Permissions::BUSINESS_OWNER);
        $business = $this->ownedBusiness($owner, ['status' => BusinessStatus::Suspended]);

        $this->actingAs($owner)->get('/account/businesses/'.$business->uuid.'/edit')->assertForbidden();
    }

    public function test_logo_upload_is_stored_as_webp(): void
    {
        Storage::fake('public');
        $owner = $this->makeUser(Permissions::BUSINESS_OWNER);
        $business = $this->ownedBusiness($owner);

        $this->actingAs($owner)->post('/account/businesses/'.$business->uuid.'/logo', [
            'logo' => UploadedFile::fake()->image('logo.jpg', 800, 800),
        ])->assertRedirect()->assertSessionHas('success');

        $path = $business->fresh()->logo_path;
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_non_image_uploads_are_rejected(): void
    {
        Storage::fake('public');
        $owner = $this->makeUser(Permissions::BUSINESS_OWNER);
        $business = $this->ownedBusiness($owner);

        $this->actingAs($owner)->post('/account/businesses/'.$business->uuid.'/cover', [
            'cover' => UploadedFile::fake()->create('cover.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors('cover');
        $this->assertNull($business->fresh()->cover_path);
    }

    public function test_gallery_respects_the_configured_limit(): void
    {
        Storage::fake('public');
        app(SettingsService::class)->set('uploads.max_gallery_images', 2);
        $owner = $this->makeUser(Permissions::BUSINESS_OWNER);
        $business = $this->ownedBusiness($owner);
        $url = '/account/businesses/'.$business->uuid.'/gallery';

        $this->actingAs($owner)->post($url, ['images' => [
            UploadedFile::fake()->image('a.png', 400, 300), UploadedFile::fake()->image('b.png', 400, 300), UploadedFile::fake()->image('c.png', 400, 300),
        ]])->assertSessionHasErrors('images');

        $this->actingAs($owner)->post($url, ['images' => [UploadedFile::fake()->image('a.png', 400, 300), UploadedFile::fake()->image('b.png', 400, 300)]])
            ->assertSessionHas('success');
        $this->assertSame(2, $business->images()->count());

        $this->actingAs($owner)->post($url, ['images' => [UploadedFile::fake()->image('d.png', 400, 300)]])->assertSessionHas('error');
        $this->assertSame(2, $business->images()->count());

        // Reorder and delete
        [$first, $second] = $business->images()->pluck('id')->all();
        $this->actingAs($owner)->post('/account/businesses/'.$business->uuid.'/gallery/reorder', ['image' => $second, 'direction' => 'up']);
        $this->assertSame($second, $business->images()->first()->id);
        $this->actingAs($owner)->delete('/account/businesses/'.$business->uuid.'/gallery/'.$first)->assertSessionHas('success');
        $this->assertSame(1, $business->images()->count());
    }

    public function test_image_from_another_business_cannot_be_deleted(): void
    {
        Storage::fake('public');
        $owner = $this->makeUser(Permissions::BUSINESS_OWNER);
        $mine = $this->ownedBusiness($owner);
        $other = $this->ownedBusiness($this->makeUser(Permissions::BUSINESS_OWNER));
        $image = $other->images()->create(['disk' => 'public', 'path' => 'x/y.webp', 'sort_order' => 0]);

        $this->actingAs($owner)->delete('/account/businesses/'.$mine->uuid.'/gallery/'.$image->id)->assertNotFound();
        $this->assertNotNull($image->fresh());
    }

    public function test_owner_can_archive_and_delete_draft(): void
    {
        $owner = $this->makeUser(Permissions::BUSINESS_OWNER);
        $published = $this->ownedBusiness($owner, ['status' => BusinessStatus::Approved, 'published_at' => now()]);
        $draft = $this->ownedBusiness($owner);

        $this->actingAs($owner)->post('/account/businesses/'.$published->uuid.'/archive')->assertSessionHas('success');
        $this->assertSame(BusinessStatus::Archived, $published->fresh()->status);

        $this->actingAs($owner)->delete('/account/businesses/'.$draft->uuid)->assertRedirect(route('ar.account.businesses.index'));
        $this->assertSoftDeleted($draft);
    }

    public function test_dashboard_shows_real_statistics(): void
    {
        $owner = $this->makeUser(Permissions::BUSINESS_OWNER);
        $business = $this->ownedBusiness($owner, ['status' => BusinessStatus::Approved, 'published_at' => now()]);
        \App\Models\BusinessEvent::query()->create(['business_id' => $business->id, 'type' => 'view']);
        \App\Models\BusinessEvent::query()->create(['business_id' => $business->id, 'type' => 'call']);

        $this->actingAs($owner)->get('/en/account')->assertOk()
            ->assertViewHas('stats', fn ($s) => $s['listings'] === 1 && $s['views'] === 1 && $s['interactions'] === 1);
        $this->actingAs($owner)->get('/en/account/businesses')->assertOk()->assertSee($business->name_en);
    }

    public function test_verification_request_stores_private_document(): void
    {
        Storage::fake('local');
        Notification::fake();
        $owner = $this->makeUser(Permissions::BUSINESS_OWNER);
        $business = $this->ownedBusiness($owner, ['status' => BusinessStatus::Approved, 'published_at' => now()]);

        $this->actingAs($owner)->get('/account/businesses/'.$business->uuid.'/verification')->assertOk();
        $this->actingAs($owner)->post('/account/businesses/'.$business->uuid.'/verification', [
            'commercial_registration' => '12345',
            'document' => UploadedFile::fake()->create('cr.pdf', 120, 'application/pdf'),
        ])->assertRedirect(route('ar.account.businesses.show', $business->uuid));

        $request = $business->verificationRequests()->firstOrFail();
        Storage::disk('local')->assertExists($request->document_path);
        $this->assertFalse($business->fresh()->is_verified);
    }
}
