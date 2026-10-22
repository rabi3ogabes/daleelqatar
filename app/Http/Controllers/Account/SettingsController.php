<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public const PREFERENCES = ['email', 'listing_updates', 'reviews', 'quotes', 'monthly_report'];

    public function edit(Request $request): View
    {
        return view('account.settings', ['user' => $request->user(), 'preferences' => self::PREFERENCES]);
    }

    public function profile(Request $request, MediaService $media, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'locale' => ['required', Rule::in(['ar', 'en'])],
            'avatar' => ['nullable', 'file', 'image', 'mimetypes:'.implode(',', MediaService::IMAGE_MIMES), 'max:'.(int) setting('uploads.max_image_kb', 5120)],
            'remove_avatar' => ['nullable', 'boolean'],
        ]);

        $original = $user->getAttributes();
        $emailChanged = $data['email'] !== $user->email;

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => filled($data['phone'] ?? null) ? preg_replace('/[^\d+]/', '', $data['phone']) : null,
            'locale' => $data['locale'],
        ]);
        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        if ($request->hasFile('avatar')) {
            $stored = $media->storeImage($request->file('avatar'), "users/{$user->uuid}", 400, 400, 85);
            $media->delete($user->avatar_path);
            $user->avatar_path = $stored['path'];
        } elseif ($request->boolean('remove_avatar')) {
            $media->delete($user->avatar_path);
            $user->avatar_path = null;
        }

        $user->save();
        $audit->logChanges('user.profile_updated', $user, $original);

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();

            return redirect()->route('verification.notice')->with('success', __('account.settings.email_changed'));
        }

        return back()->with('success', __('account.settings.profile_saved'));
    }

    public function password(Request $request, AuditLogger $audit): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $request->input('password')])->save();
        Auth::logoutOtherDevices($request->input('password'));
        $audit->log('user.password_changed', $user, [], [], [], $user->id);

        return back()->with('success', __('account.settings.password_saved'));
    }

    public function preferences(Request $request): RedirectResponse
    {
        $prefs = [];
        foreach (self::PREFERENCES as $key) {
            $prefs[$key] = $request->boolean('prefs.'.$key);
        }
        $request->user()->forceFill(['notification_preferences' => $prefs])->save();

        return back()->with('success', __('account.settings.preferences_saved'));
    }

    public function destroy(Request $request, AuditLogger $audit, MediaService $media): RedirectResponse
    {
        $request->validateWithBag('deleteAccount', ['delete_password' => ['required', 'current_password']]);

        $user = $request->user();
        DB::transaction(function () use ($user, $audit, $media) {
            $audit->log('user.deleted_self', $user, [], [], [], $user->id);
            // Listings stay in the directory but are no longer linked to this account.
            $user->businesses()->update(['owner_id' => null]);
            $user->tokens()->delete();
            $media->delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();
            $user->delete();
        });

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', __('account.settings.deleted'));
    }
}
