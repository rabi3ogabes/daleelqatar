<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        seo()->title(__('auth_ui.login_title'))->noindex();

        return view('auth.login');
    }

    public function store(LoginRequest $request, AuditLogger $audit): RedirectResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = User::query()->where('email', $credentials['email'])->first();
            // Never log the submitted password.
            $audit->log('auth.login_failed', $user, [], [], ['email' => $credentials['email']], $user?->id);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        /** @var User $user */
        $user = Auth::user();

        if ($user->isSuspended()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $audit->log('auth.login_blocked', $user, [], [], ['reason' => 'suspended'], $user->id);

            throw ValidationException::withMessages(['email' => __('auth.suspended')]);
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        $audit->log('auth.login', $user, [], [], ['remember' => $request->boolean('remember')], $user->id);

        $default = $user->can('admin.access') ? route('admin.dashboard') : route('account.dashboard');

        return redirect()->intended($default);
    }

    public function destroy(Request $request, AuditLogger $audit): RedirectResponse
    {
        if ($user = $request->user()) {
            $audit->log('auth.logout', $user, [], [], [], $user->id);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', __('auth.logged_out'));
    }
}
