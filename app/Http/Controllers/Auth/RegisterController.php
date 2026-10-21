<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Permissions;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        seo()->title(__('auth_ui.register_title'))->noindex();

        return view('auth.register');
    }

    public function store(RegisterRequest $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'locale' => app()->getLocale(),
        ]);
        $user->assignRole(Permissions::USER);

        event(new Registered($user)); // sends the verification e-mail
        $audit->log('auth.registered', $user, [], ['email' => $user->email], [], $user->id);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice')->with('success', __('auth.welcome'));
    }
}
