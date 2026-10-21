<?php

namespace Tests\Feature\Admin;

use App\Enums\BusinessStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\DashboardMetrics;
use App\Services\SettingsService;

class DashboardAndSettingsTest extends AdminTestCase
{
    public function test_dashboard_figures_match_real_counts(): void
    {
        $this->completeBusiness(BusinessStatus::Approved, attributes: ['published_at' => now()]);
        $this->completeBusiness(BusinessStatus::Approved, attributes: ['published_at' => now()]);
        $this->completeBusiness(BusinessStatus::Pending);
        $this->completeBusiness(BusinessStatus::Rejected);
        $this->completeBusiness(BusinessStatus::Suspended);
        $super = $this->superAdmin();

        $totals = app(DashboardMetrics::class)->totals();

        $this->assertSame(5, $totals['businesses']);
        $this->assertSame(2, $totals['approved']);
        $this->assertSame(1, $totals['pending']);
        $this->assertSame(2, $totals['rejected_suspended']);
        $this->assertSame(User::query()->count(), $totals['users']);
        $this->assertSame(5, $totals['owners']);

        $this->actingAs($super)->get('/en/admin')->assertOk()
            ->assertSeeInOrder(['Total businesses', '5'])
            ->assertSeeInOrder(['Pending review', '1']);
    }

    public function test_trend_is_null_without_a_baseline_and_computed_with_one(): void
    {
        $metrics = app(DashboardMetrics::class);
        $this->assertNull($metrics->trends()['businesses']);

        $old = $this->completeBusiness(BusinessStatus::Draft);
        $old->forceFill(['created_at' => now()->subDays(45)])->save();
        $this->completeBusiness(BusinessStatus::Draft);
        $this->completeBusiness(BusinessStatus::Draft);

        $this->assertSame(100.0, $metrics->trends()['businesses']);
    }

    public function test_settings_update_persists_and_is_audited(): void
    {
        $super = $this->superAdmin();

        $this->actingAs($super)->put(route('ar.admin.settings.update', 'moderation'), [
            's' => [
                'listings__auto_approve' => '1',
                'listings__reapprove_on_edit' => '0',
                'listings__max_per_owner' => '25',
                'reviews__enabled' => '1',
                'reviews__require_moderation' => '1',
                'reviews__owner_replies' => '0',
                'reviews__min_length' => '30',
                'offers__require_moderation' => '1',
            ],
        ])->assertRedirect(route('ar.admin.settings.edit', 'moderation'));

        $settings = app(SettingsService::class);
        $settings->flush();
        $this->assertTrue($settings->bool('listings.auto_approve'));
        $this->assertSame(25, $settings->int('listings.max_per_owner'));
        $this->assertFalse($settings->bool('reviews.owner_replies'));

        $log = AuditLog::query()->where('action', 'settings.updated')->latest('id')->firstOrFail();
        $this->assertArrayHasKey('listings.auto_approve', $log->new_values);
        $this->assertSame('moderation', $log->metadata['group']);
    }

    public function test_settings_validation_rejects_bad_values(): void
    {
        $this->actingAs($this->superAdmin())->from('/admin/settings/contact')->put(route('ar.admin.settings.update', 'contact'), [
            's' => ['contact__email' => 'not-an-email', 'social__instagram' => 'javascript:alert(1)'],
        ])->assertSessionHasErrors(['s.contact__email', 's.social__instagram']);
    }

    public function test_homepage_content_is_editable(): void
    {
        $this->actingAs($this->superAdmin())->put(route('ar.admin.homepage.update'), [
            'hero_title' => ['ar' => 'عنوان جديد للواجهة', 'en' => 'A brand new headline'],
            'hero_subtitle' => ['ar' => 'نص', 'en' => 'Text'],
            'show_offers' => '1',
        ])->assertRedirect();

        app(SettingsService::class)->flush();
        $this->get('/en')->assertOk()->assertSee('A brand new headline');
        $this->get('/')->assertOk()->assertSee('عنوان جديد للواجهة');
    }
}
