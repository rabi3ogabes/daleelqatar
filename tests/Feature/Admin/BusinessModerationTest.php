<?php

namespace Tests\Feature\Admin;

use App\Enums\BusinessStatus;
use App\Models\AuditLog;
use App\Models\BusinessStatusChange;
use App\Notifications\ListingStatusChanged;
use Illuminate\Support\Facades\Notification;

class BusinessModerationTest extends AdminTestCase
{
    public function test_admin_approval_publishes_the_listing_and_records_history(): void
    {
        $business = $this->completeBusiness();
        $this->get('/business/'.$business->slug)->assertNotFound();

        $admin = $this->admin();
        $this->actingAs($admin)->post(route('ar.admin.businesses.status', $business->id), ['action' => 'approve'])->assertRedirect();

        $business->refresh();
        $this->assertSame(BusinessStatus::Approved, $business->status);
        $this->assertNotNull($business->published_at);
        $this->assertDatabaseHas('business_status_changes', ['business_id' => $business->id, 'from_status' => 'pending', 'to_status' => 'approved', 'user_id' => $admin->id]);
        $this->assertTrue(AuditLog::query()->where('action', 'business.status.approved')->where('auditable_id', $business->id)->exists());

        auth()->logout();
        $this->get('/business/'.$business->slug)->assertOk()->assertSee($business->name_ar);
    }

    public function test_rejection_requires_a_reason_and_notifies_the_owner(): void
    {
        Notification::fake();
        $business = $this->completeBusiness();
        $admin = $this->admin();

        $this->actingAs($admin)->from('/admin/businesses/'.$business->id)
            ->post(route('ar.admin.businesses.status', $business->id), ['action' => 'reject'])
            ->assertSessionHasErrors('reason');
        $this->assertSame(BusinessStatus::Pending, $business->fresh()->status);

        $this->actingAs($admin)->post(route('ar.admin.businesses.status', $business->id), ['action' => 'reject', 'reason' => 'Please add a clear description.'])->assertRedirect();

        $this->assertSame(BusinessStatus::Rejected, $business->fresh()->status);
        $this->assertSame('Please add a clear description.', $business->fresh()->rejection_reason);
        Notification::assertSentTo($business->owner, ListingStatusChanged::class, fn ($n) => $n->event === 'rejected');
    }

    public function test_suspension_hides_a_published_listing(): void
    {
        $business = $this->completeBusiness(BusinessStatus::Approved, attributes: ['published_at' => now()]);
        $this->get('/business/'.$business->slug)->assertOk();

        $this->actingAs($this->moderator())->post(route('ar.admin.businesses.status', $business->id), ['action' => 'suspend', 'reason' => 'Under investigation'])->assertRedirect();
        auth()->logout();

        $this->assertSame(BusinessStatus::Suspended, $business->fresh()->status);
        $this->get('/business/'.$business->slug)->assertNotFound();
    }

    public function test_invalid_transition_is_refused(): void
    {
        $business = $this->completeBusiness(BusinessStatus::Draft);
        $this->actingAs($this->admin())->post(route('ar.admin.businesses.status', $business->id), ['action' => 'restore'])
            ->assertSessionHas('error');
        $this->assertSame(BusinessStatus::Draft, $business->fresh()->status);
        $this->assertSame(0, BusinessStatusChange::query()->where('business_id', $business->id)->count());
    }

    public function test_feature_and_verify_toggles_are_audited(): void
    {
        $business = $this->completeBusiness(BusinessStatus::Approved, attributes: ['published_at' => now()]);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('ar.admin.businesses.feature', $business->id), ['featured' => 1, 'featured_until' => now()->addMonth()->toDateString()])->assertRedirect();
        $this->actingAs($admin)->post(route('ar.admin.businesses.verify', $business->id), ['verified' => 1])->assertRedirect();

        $business->refresh();
        $this->assertTrue($business->isFeaturedNow());
        $this->assertTrue($business->is_verified);
        $this->assertSame($admin->id, $business->verified_by);
        $this->assertTrue(AuditLog::query()->where('action', 'business.featured')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'business.verified')->exists());
    }

    public function test_moderator_cannot_delete_or_export(): void
    {
        $business = $this->completeBusiness();
        $moderator = $this->moderator();

        $this->actingAs($moderator)->delete(route('ar.admin.businesses.destroy', $business->id))->assertForbidden();
        $this->actingAs($moderator)->get(route('ar.admin.businesses.export'))->assertForbidden();
        $this->assertNotSoftDeleted($business);
    }

    public function test_admin_can_create_and_publish_a_listing_with_an_owner(): void
    {
        $owner = $this->makeUser();
        $response = $this->actingAs($this->admin())->post(route('ar.admin.businesses.store'), [
            'name_ar' => 'مقهى الاختبار', 'name_en' => 'Test Roastery',
            'category_id' => \App\Models\Category::query()->where('slug', 'cafes')->value('id'),
            'municipality_id' => \App\Models\Municipality::query()->where('slug', 'doha')->value('id'),
            'description_ar' => 'وصف عربي كامل للنشاط.', 'description_en' => 'A complete English description.',
            'phone' => '44000000', 'owner_email' => $owner->email, 'publish' => 1,
        ]);

        $business = \App\Models\Business::query()->where('name_en', 'Test Roastery')->firstOrFail();
        $response->assertRedirect(route('ar.admin.businesses.show', $business->id));
        $this->assertSame($owner->id, $business->owner_id);
        $this->assertSame('+97444000000', $business->phone);
        $this->assertTrue($business->isPublished());
        $this->assertTrue($owner->fresh()->hasRole('business-owner'));
    }

    public function test_csv_export_contains_rows_and_is_audited(): void
    {
        $business = $this->completeBusiness(BusinessStatus::Approved, attributes: ['published_at' => now(), 'name_en' => 'Exported Café']);

        $response = $this->actingAs($this->admin())->get(route('ar.admin.businesses.export', ['status' => 'approved']));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();

        $this->assertStringContainsString('Exported Café', $csv);
        $this->assertStringContainsString($business->slug, $csv);
        $this->assertStringNotContainsString('proof_path', $csv);
        $this->assertTrue(AuditLog::query()->where('action', 'business.exported')->exists());
    }
}
