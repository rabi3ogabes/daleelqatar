<?php

namespace Tests\Feature\Admin;

use App\Support\Permissions;

class AdminAccessTest extends AdminTestCase
{
    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect(route('ar.login'));
    }

    public function test_regular_users_and_owners_are_forbidden(): void
    {
        $this->actingAs($this->makeUser(Permissions::USER))->get('/admin')->assertForbidden();
        $this->actingAs($this->makeUser(Permissions::BUSINESS_OWNER))->get('/en/admin/businesses')->assertForbidden();
    }

    public function test_moderator_can_open_queues_but_not_settings_or_roles(): void
    {
        $moderator = $this->moderator();

        $this->actingAs($moderator)->get('/admin')->assertOk();
        $this->actingAs($moderator)->get('/admin/reviews')->assertOk();
        $this->actingAs($moderator)->get('/admin/businesses')->assertOk();
        $this->actingAs($moderator)->get('/admin/settings')->assertForbidden();
        $this->actingAs($moderator)->get('/admin/roles')->assertForbidden();
        $this->actingAs($moderator)->get('/admin/users')->assertForbidden();
        $this->actingAs($moderator)->get('/admin/categories')->assertForbidden();
    }

    public function test_admin_cannot_manage_roles_or_settings(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/users')->assertOk();
        $this->actingAs($admin)->get('/admin/roles')->assertForbidden();
        $this->actingAs($admin)->get('/admin/settings')->assertForbidden();
    }

    /** Renders every admin screen in both languages to catch view, query and lazy-loading errors. */
    public function test_every_admin_page_renders_in_both_languages(): void
    {
        $business = $this->completeBusiness();
        $user = $this->superAdmin();
        $this->actingAs($user);

        $pages = [
            '/admin', '/admin/businesses', '/admin/businesses/create', '/admin/businesses/'.$business->id, '/admin/businesses/'.$business->id.'/edit',
            '/admin/claims', '/admin/verifications', '/admin/categories', '/admin/categories/create', '/admin/categories/restaurants-cafes/edit',
            '/admin/municipalities', '/admin/municipalities/create', '/admin/municipalities/doha/edit', '/admin/municipalities/doha/areas/create', '/admin/areas/west-bay/edit',
            '/admin/users', '/admin/users/'.$user->id, '/admin/roles', '/admin/reviews', '/admin/reports', '/admin/reports?tab=reviews',
            '/admin/offers', '/admin/offers/create', '/admin/pages', '/admin/faqs', '/admin/faqs/create', '/admin/banners', '/admin/banners/create',
            '/admin/homepage', '/admin/messages', '/admin/settings', '/admin/settings/moderation', '/admin/settings/seo', '/admin/analytics', '/admin/analytics?days=90',
            '/admin/audit-logs', '/admin/notifications',
        ];

        foreach ($pages as $path) {
            foreach (['', '/en'] as $prefix) {
                $this->get($prefix.$path)->assertOk();
            }
        }
    }
}
