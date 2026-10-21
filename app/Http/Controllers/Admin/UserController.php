<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $users = User::query()->with('roles:id,name')->withCount('businesses')
            ->when(trim((string) $request->query('q')), function (Builder $q, string $term) {
                $like = '%'.addcslashes(mb_strtolower($term), '%_\\').'%';
                $q->where(fn ($w) => $w->whereRaw('lower(name) LIKE ?', [$like])->orWhereRaw('lower(email) LIKE ?', [$like])->orWhere('phone', 'like', $like));
            })
            ->when(in_array($request->query('role'), Permissions::roles(), true), fn ($q) => $q->role($request->query('role')))
            ->when(UserStatus::tryFrom((string) $request->query('status')), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('verified') === '0', fn ($q) => $q->whereNull('email_verified_at'))
            ->latest()->paginate(25)->withQueryString();

        return view('admin.users.index', ['users' => $users]);
    }

    public function show(Request $request, User $user): View
    {
        $this->authorize('view', $user);
        $user->load(['roles:id,name', 'businesses' => fn ($q) => $q->withTrashed()->with('category:id,name_ar,name_en,icon')->latest()]);

        $activity = AuditLog::query()
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere(fn ($w) => $w->where('auditable_type', $user->getMorphClass())->where('auditable_id', $user->id)))
            ->with('user:id,name')->latest('created_at')->latest('id')->limit(15)->get();

        $actor = $request->user();
        $assignable = collect(Permissions::RANKS)->filter(fn ($rank) => $rank < $actor->roleRank())->keys()->values();

        return view('admin.users.show', [
            'user' => $user,
            'reviews' => $user->reviews()->with('business:id,name_ar,name_en,slug')->latest()->limit(10)->get(),
            'activity' => $activity,
            'assignable' => $assignable,
            'tokens' => DB::table('personal_access_tokens')->where('tokenable_type', $user->getMorphClass())->where('tokenable_id', $user->id)->count(),
        ]);
    }

    public function roles(Request $request, User $user): RedirectResponse
    {
        $this->authorize('assignRoles', $user);
        $actor = $request->user();
        $allowed = collect(Permissions::RANKS)->filter(fn ($rank) => $rank < $actor->roleRank())->keys()->all();

        $data = $request->validate([
            'roles' => ['array'],
            'roles.*' => ['string', Rule::in($allowed)],
        ]);

        $old = $user->getRoleNames()->all();
        // Roles the actor is not allowed to manage are preserved untouched.
        $protected = array_values(array_diff($old, $allowed));
        $new = array_values(array_unique(array_merge($protected, $data['roles'] ?? [])));
        $user->syncRoles($new);

        $this->audit->log('user.roles.updated', $user, ['roles' => $old], ['roles' => $new]);

        return back()->with('success', __('admin.users.roles_updated'));
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        $this->authorize('suspend', $user);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        DB::transaction(function () use ($user, $data) {
            $user->forceFill(['status' => UserStatus::Suspended, 'suspended_at' => now(), 'suspension_reason' => $data['reason']])->save();
            $user->tokens()->delete(); // revoke every API/mobile token immediately
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });
        $this->audit->log('user.suspended', $user, ['status' => 'active'], ['status' => 'suspended'], ['reason' => $data['reason']]);

        return back()->with('success', __('admin.users.suspended'));
    }

    public function unsuspend(User $user): RedirectResponse
    {
        $this->authorize('suspend', $user);
        $user->forceFill(['status' => UserStatus::Active, 'suspended_at' => null, 'suspension_reason' => null])->save();
        $this->audit->log('user.unsuspended', $user, ['status' => 'suspended'], ['status' => 'active']);

        return back()->with('success', __('admin.users.unsuspended'));
    }

    public function sendPasswordReset(User $user): RedirectResponse
    {
        $this->authorize('sendPasswordReset', $user);
        $status = Password::sendResetLink(['email' => $user->email]);
        $this->audit->log('user.password_reset_sent', $user);

        return back()->with($status === Password::RESET_LINK_SENT ? 'success' : 'error', __($status));
    }
}
