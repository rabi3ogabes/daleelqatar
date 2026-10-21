<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Permissions;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * Token authentication for mobile clients (Laravel Sanctum personal access tokens).
 */
class AuthController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:191', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
            'device_name' => ['required', 'string', 'max:100'],
            'locale' => ['nullable', Rule::in(['ar', 'en'])],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9\s\-()]{7,32}$/'],
        ]);

        $emailTaken = User::withTrashed()->whereRaw('lower(email) = ?', [mb_strtolower($data['email'])])->exists();
        if ($emailTaken) {
            throw ValidationException::withMessages(['email' => __('validation.unique', ['attribute' => __('validation.attributes.email')])]);
        }

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'locale' => $data['locale'] ?? app()->getLocale(),
        ]);
        $user->assignRole(Permissions::USER);

        event(new Registered($user));
        $this->audit->log('auth.registered', $user, [], [], ['channel' => 'api'], $user->id);

        return $this->tokenResponse($user, $data['device_name'], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($data['email']))])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            $this->audit->log('auth.login_failed', $user, [], [], ['channel' => 'api', 'email' => mb_strtolower($data['email'])], $user?->id);

            return response()->json(['message' => __('auth.failed'), 'code' => 'invalid_credentials'], 401);
        }

        if ($user->isSuspended()) {
            $this->audit->log('auth.login_blocked', $user, [], [], ['channel' => 'api', 'reason' => 'suspended'], $user->id);

            return response()->json(['message' => __('auth.suspended'), 'code' => 'account_suspended'], 403);
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        $this->audit->log('auth.login', $user, [], [], ['channel' => 'api', 'device' => $data['device_name']], $user->id);

        return $this->tokenResponse($user, $data['device_name']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => __('api.logged_out')]);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();
        $this->audit->log('auth.tokens_revoked', $request->user());

        return response()->json(['message' => __('api.logged_out')]);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => __('api_v1.already_verified')]);
        }
        $user->sendEmailVerificationNotification();

        return response()->json(['message' => __('auth.verification_sent')]);
    }

    /** Always answers with the same generic message so accounts cannot be enumerated. */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        return response()->json(['message' => __('api_v1.reset_link_generic')]);
    }

    private function tokenResponse(User $user, string $device, int $status = 200): JsonResponse
    {
        $minutes = config('sanctum.expiration');
        $expiresAt = $minutes ? now()->addMinutes((int) $minutes) : null;
        $token = $user->createToken(mb_substr($device, 0, 100), ['*'], $expiresAt);

        return response()->json(['data' => [
            'user' => new UserResource($user->fresh()),
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt?->toIso8601String(),
        ]], $status);
    }
}
