<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function request(): View
    {
        seo()->title(__('auth_ui.forgot_title'))->noindex();

        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $request->validate(['email' => ['required', 'email', 'max:191']]);

        $status = Password::sendResetLink($request->only('email'));

        // Same response whether or not the address exists (prevents account enumeration),
        // except when the broker throttled the request.
        if ($status === Password::RESET_THROTTLED) {
            return back()->withInput()->withErrors(['email' => __($status)]);
        }

        return back()->with('status', __(Password::RESET_LINK_SENT));
    }

    public function reset(Request $request, string $token): View
    {
        seo()->title(__('auth_ui.reset_title'))->noindex();

        return view('auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($audit) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                // Revoke API tokens so a compromised device loses access.
                $user->tokens()->delete();
                $audit->log('auth.password_reset', $user, [], [], [], $user->id);
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }
}
