<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::query()->with('permissions:id,name')->withCount('users')->get()
            ->sortByDesc(fn (Role $r) => Permissions::RANKS[$r->name] ?? 0)->values();

        return view('admin.roles.index', [
            'roles' => $roles,
            'permissions' => Permission::query()->orderBy('name')->pluck('name')->all(),
        ]);
    }

    public function update(Request $request, Role $role, AuditLogger $audit): RedirectResponse
    {
        // The super-administrator role always holds every permission.
        abort_if($role->name === Permissions::SUPER_ADMIN, 403);

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(Permissions::ALL)],
        ]);

        $old = $role->permissions->pluck('name')->sort()->values()->all();
        $role->syncPermissions($data['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $audit->log('role.permissions.updated', $role, ['permissions' => $old], ['permissions' => collect($data['permissions'] ?? [])->sort()->values()->all()]);

        return back()->with('success', __('admin.roles.updated', ['role' => __('dashboard.roles.'.$role->name)]));
    }
}
