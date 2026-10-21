<?php

namespace Tests\Feature\Admin;

use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Support\Permissions;

class UserManagementTest extends AdminTestCase
{
    public function test_admin_cannot_grant_super_admin_or_admin(): void
    {
        $admin = $this->admin();
        $target = $this->makeUser();

        $this->actingAs($admin)->from('/admin/users/'.$target->id)
            ->put(route('ar.admin.users.roles', $target), ['roles' => [Permissions::SUPER_ADMIN]])
            ->assertSessionHasErrors('roles.0');
        $this->actingAs($admin)->from('/admin/users/'.$target->id)
            ->put(route('ar.admin.users.roles', $target), ['roles' => [Permissions::ADMIN]])
            ->assertSessionHasErrors('roles.0');
        $this->assertFalse($target->fresh()->hasAnyRole([Permissions::SUPER_ADMIN, Permissions::ADMIN]));

        $this->actingAs($admin)->put(route('ar.admin.users.roles', $target), ['roles' => [Permissions::MODERATOR, Permissions::USER]])->assertRedirect();
        $this->assertTrue($target->fresh()->hasRole(Permissions::MODERATOR));
        $this->assertTrue(AuditLog::query()->where('action', 'user.roles.updated')->where('auditable_id', $target->id)->exists());
    }

    public function test_nobody_can_change_their_own_roles(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put(route('ar.admin.users.roles', $admin), ['roles' => [Permissions::MODERATOR]])->assertForbidden();

        $super = $this->superAdmin();
        $this->actingAs($super)->put(route('ar.admin.users.roles', $super), ['roles' => []])->assertForbidden();
        $this->assertTrue($super->fresh()->hasRole(Permissions::SUPER_ADMIN));
    }

    public function test_admin_cannot_manage_an_equal_or_higher_ranked_account(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin();

        $this->actingAs($admin)->post(route('ar.admin.users.suspend', $otherAdmin), ['reason' => 'x'])->assertForbidden();
        $this->actingAs($admin)->put(route('ar.admin.users.roles', $otherAdmin), ['roles' => []])->assertForbidden();
    }

    public function test_suspending_a_user_revokes_api_tokens(): void
    {
        $target = $this->makeUser();
        $target->createToken('flutter-ios');
        $target->createToken('flutter-android');
        $this->assertSame(2, $target->tokens()->count());

        $this->actingAs($this->admin())->post(route('ar.admin.users.suspend', $target), ['reason' => 'Spam reviews'])->assertRedirect();

        $target->refresh();
        $this->assertSame(UserStatus::Suspended, $target->status);
        $this->assertSame(0, $target->tokens()->count());
        $this->assertTrue(AuditLog::query()->where('action', 'user.suspended')->exists());

        $this->actingAs($this->admin())->post(route('ar.admin.users.unsuspend', $target))->assertRedirect();
        $this->assertSame(UserStatus::Active, $target->fresh()->status);
    }

    public function test_only_super_admin_edits_role_permissions_and_super_admin_role_is_locked(): void
    {
        $super = $this->superAdmin();
        $moderatorRole = \Spatie\Permission\Models\Role::findByName(Permissions::MODERATOR);
        $superRole = \Spatie\Permission\Models\Role::findByName(Permissions::SUPER_ADMIN);

        $this->actingAs($this->admin())->put(route('ar.admin.roles.update', $moderatorRole), ['permissions' => ['admin.access']])->assertForbidden();
        $this->actingAs($super)->put(route('ar.admin.roles.update', $superRole), ['permissions' => []])->assertForbidden();

        $this->actingAs($super)->put(route('ar.admin.roles.update', $moderatorRole), ['permissions' => ['admin.access', 'reviews.moderate']])->assertRedirect();
        $this->assertEqualsCanonicalizing(['admin.access', 'reviews.moderate'], $moderatorRole->fresh()->permissions->pluck('name')->all());
    }
}
