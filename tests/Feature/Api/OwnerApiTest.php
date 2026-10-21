<?php

namespace Tests\Feature\Api;

use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\Category;
use App\Models\Municipality;
use App\Models\User;
use App\Notifications\ListingStatusChanged;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

class OwnerApiTest extends ApiTestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name_ar' => 'مقهى اللؤلؤة',
            'name_en' => 'Pearl Cafe',
            'category_id' => Category::query()->where('slug', 'cafes')->value('id'),
            'municipality_id' => Municipality::query()->where('slug', 'doha')->value('id'),
            'description_ar' => 'مقهى مختص بالقهوة العربية.',
            'description_en' => 'A specialty Arabic coffee house.',
            'phone' => '44001122',
            'social' => ['instagram' => 'https://instagram.com/pearlcafe'],
            'hours' => ['0' => ['periods' => [['opens' => '08:00', 'closes' => '22:00']]], '5' => ['closed' => true]],
            'services' => [['name_ar' => 'قهوة', 'name_en' => 'Coffee', 'price_from' => 15]],
        ], $overrides);
    }

    public function test_unverified_user_cannot_create_business(): void
    {
        Sanctum::actingAs(User::factory()->unverified()->create());

        $this->postJson('/api/v1/me/businesses', $this->payload())->assertStatus(403)->assertJsonPath('code', 'email_unverified');
    }

    public function test_owner_create_update_submit_flow(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $created = $this->postJson('/api/v1/me/businesses', $this->payload())->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.contact.phone', '+97444001122')
            ->assertJsonPath('data.missing_requirements', [])
            ->assertJsonCount(2, 'data.opening_hours')
            ->json('data');
        $this->assertTrue($user->fresh()->hasRole('business-owner'));

        // PATCH only touches the fields that were sent.
        $this->patchJson('/api/v1/me/businesses/'.$created['id'], ['tagline_en' => 'Best coffee in Doha'])->assertOk()
            ->assertJsonPath('data.contact.phone', '+97444001122')
            ->assertJsonPath('data.social_links.0.platform', 'instagram')
            ->assertJsonPath('data.translations.tagline.en', 'Best coffee in Doha');

        $this->postJson('/api/v1/me/businesses/'.$created['id'].'/submit')->assertOk()->assertJsonPath('data.status', 'pending');
        $this->getJson('/api/v1/me/businesses')->assertOk()->assertJsonPath('data.0.status', 'pending');

        // Pending listings are still private to everyone except the owner and staff.
        $this->resetAuth();
        $this->getJson('/api/v1/businesses/'.$created['id'])->assertStatus(404);
        $this->assertSame(0, $this->getJson('/api/v1/businesses')->json('meta.total'));
    }

    public function test_incomplete_listing_cannot_be_submitted(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/v1/me/businesses', $this->payload(['description_en' => null, 'phone' => null, 'social' => []]))->assertCreated()->json('data.id');

        $this->postJson("/api/v1/me/businesses/{$id}/submit")->assertStatus(422)->assertJsonPath('code', 'workflow_error');
    }

    public function test_owner_cannot_modify_another_owners_business(): void
    {
        $owner = $this->owner();
        $business = Business::factory()->create(['owner_id' => $owner->id]);
        Sanctum::actingAs($this->owner());

        $this->patchJson('/api/v1/me/businesses/'.$business->uuid, ['name_en' => 'Hijacked'])->assertStatus(403)->assertJsonPath('code', 'forbidden');
        $this->deleteJson('/api/v1/me/businesses/'.$business->uuid)->assertStatus(403);
        $this->getJson('/api/v1/me/businesses/'.$business->uuid)->assertStatus(403);
        $this->postJson('/api/v1/me/businesses/'.$business->uuid.'/submit')->assertStatus(403);
        $this->assertSame($business->name_en, $business->fresh()->name_en);
    }

    public function test_owner_can_delete_draft(): void
    {
        $owner = $this->owner();
        $business = Business::factory()->create(['owner_id' => $owner->id]);
        Sanctum::actingAs($owner);

        $this->deleteJson('/api/v1/me/businesses/'.$business->uuid)->assertNoContent();
        $this->assertSoftDeleted($business);
    }

    public function test_logo_upload_is_stored_as_webp(): void
    {
        Storage::fake('public');
        $owner = $this->owner();
        $business = Business::factory()->create(['owner_id' => $owner->id]);
        Sanctum::actingAs($owner);

        $response = $this->post('/api/v1/me/businesses/'.$business->uuid.'/logo', [
            'logo' => UploadedFile::fake()->image('logo.jpg', 640, 640),
        ], ['Accept' => 'application/json'])->assertOk();

        $path = $business->fresh()->logo_path;
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertNotNull($response->json('data.logo_url'));

        $this->post('/api/v1/me/businesses/'.$business->uuid.'/logo', ['logo' => UploadedFile::fake()->create('evil.php', 10, 'application/x-php')], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_gallery_upload_and_delete(): void
    {
        Storage::fake('public');
        $owner = $this->owner();
        $business = Business::factory()->create(['owner_id' => $owner->id]);
        Sanctum::actingAs($owner);

        $this->post('/api/v1/me/businesses/'.$business->uuid.'/gallery', [
            'images' => [UploadedFile::fake()->image('a.png', 800, 600), UploadedFile::fake()->image('b.jpg', 800, 600)],
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('meta.added', 2)->assertJsonCount(2, 'data.gallery');

        $image = $business->images()->first();
        $this->deleteJson('/api/v1/me/businesses/'.$business->uuid.'/gallery/'.$image->id)->assertNoContent();
        Storage::disk('public')->assertMissing($image->path);
    }

    public function test_favorites_are_idempotent(): void
    {
        $business = $this->publishedBusiness();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/me/favorites/'.$business->uuid)->assertOk()->assertJsonPath('data.favorited', true);
        $this->postJson('/api/v1/me/favorites/'.$business->slug)->assertOk();
        $this->assertSame(1, $business->fresh()->favorites_count);
        $this->getJson('/api/v1/me/favorites')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/businesses/'.$business->uuid)->assertJsonPath('data.is_favorited', true);

        $this->deleteJson('/api/v1/me/favorites/'.$business->uuid)->assertOk()->assertJsonPath('data.favorited', false);
        $this->deleteJson('/api/v1/me/favorites/'.$business->uuid)->assertOk();
        $this->assertSame(0, $business->fresh()->favorites_count);
    }

    public function test_review_submission_and_duplicate_prevention(): void
    {
        Notification::fake();
        $owner = $this->owner();
        $business = $this->publishedBusiness(['owner_id' => $owner->id]);
        Sanctum::actingAs(User::factory()->create());
        $body = ['rating' => 5, 'title' => 'Excellent', 'body' => 'Wonderful coffee and very friendly service.'];

        $this->postJson('/api/v1/businesses/'.$business->uuid.'/reviews', $body)->assertCreated()->assertJsonPath('data.status', 'pending');
        $this->postJson('/api/v1/businesses/'.$business->uuid.'/reviews', $body)->assertStatus(422)->assertJsonPath('code', 'workflow_error');

        // Owners cannot review their own business.
        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/businesses/'.$business->uuid.'/reviews', $body)->assertStatus(403);
    }

    public function test_owner_offer_lifecycle_requires_ownership(): void
    {
        $owner = $this->owner();
        $business = $this->publishedBusiness(['owner_id' => $owner->id]);
        Sanctum::actingAs($owner);

        $offer = $this->postJson('/api/v1/me/offers', [
            'business_id' => $business->uuid, 'title_ar' => 'خصم', 'title_en' => 'Discount',
            'starts_at' => now()->toIso8601String(), 'ends_at' => now()->addWeek()->toIso8601String(),
        ])->assertCreated()->assertJsonPath('data.status', 'pending')->json('data');

        $this->patchJson('/api/v1/me/offers/'.$offer['id'], ['title_en' => 'Bigger discount'])->assertOk();

        Sanctum::actingAs($this->owner());
        $this->patchJson('/api/v1/me/offers/'.$offer['id'], ['title_en' => 'Hijack'])->assertStatus(403);
        $this->postJson('/api/v1/me/offers', [
            'business_id' => $business->uuid, 'title_ar' => 'x', 'title_en' => 'x',
            'starts_at' => now()->toIso8601String(), 'ends_at' => now()->addWeek()->toIso8601String(),
        ])->assertStatus(403);
    }

    public function test_claim_unowned_listing(): void
    {
        Notification::fake();
        $business = $this->publishedBusiness();
        Sanctum::actingAs(User::factory()->create());
        $data = ['claimant_name' => 'Khalid', 'phone' => '+974 5555 1234', 'email' => 'k@example.com', 'terms' => true];

        $this->postJson('/api/v1/businesses/'.$business->uuid.'/claim', $data)->assertCreated()->assertJsonPath('data.status', 'pending');
        $this->postJson('/api/v1/businesses/'.$business->uuid.'/claim', $data)->assertStatus(422)->assertJsonPath('code', 'workflow_error');
        $this->getJson('/api/v1/me/claims')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_notifications_can_be_listed_and_marked_read(): void
    {
        $owner = $this->owner();
        $business = Business::factory()->create(['owner_id' => $owner->id]);
        $owner->notify(new ListingStatusChanged($business, 'approved'));
        Sanctum::actingAs($owner);

        $id = $this->getJson('/api/v1/me/notifications')->assertOk()->assertJsonPath('meta.unread_count', 1)->json('data.0.id');
        $this->postJson("/api/v1/me/notifications/{$id}/read")->assertOk()->assertJsonPath('data.id', $id);
        $this->assertSame(0, $owner->fresh()->unreadNotifications()->count());
    }

    public function test_account_deletion_requires_password(): void
    {
        $user = User::factory()->create(['password' => 'delete-me-1']);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/me', ['password' => 'wrong'])->assertStatus(422);
        $this->deleteJson('/api/v1/me', ['password' => 'delete-me-1'])->assertNoContent();
        $this->assertSoftDeleted($user);
    }

    public function test_status_enum_after_submission(): void
    {
        $this->assertSame('pending', BusinessStatus::Pending->value);
    }
}
