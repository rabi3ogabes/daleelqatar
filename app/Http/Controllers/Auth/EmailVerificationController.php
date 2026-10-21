<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('account.dashboard'));
        }

        seo()->title(__('auth_ui.verify_title'))->noindex();

        return view('auth.verify-email');
    }

    /**
     * Works with or without a browser session, so people who registered in the
     * mobile app can verify from their e-mail. The route is signed (tamper-proof,
     * expiring) and the hash must match the account's current e-mail address.
     */
    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $current = $request->user();
        if ($current && ! hash_equals((string) $current->getKey(), $id)) {
            abort(403);
        }

        $user = $current ?? User::query()->findOrFail($id);
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return $current
            ? redirect()->intended(route('account.dashboard'))->with('success', __('auth.verified'))
            : redirect()->route('login')->with('success', __('auth.verified'));
    }

    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('account.dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', __('auth.verification_sent'));
    }
}
