<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function update(Request $request): UserResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/^\+?[0-9\s\-()]{7,32}$/'],
            'locale' => ['sometimes', Rule::in(['ar', 'en'])],
            'notification_preferences' => ['sometimes', 'array'],
            'notification_preferences.email' => ['sometimes', 'boolean'],
            'notification_preferences.listing_updates' => ['sometimes', 'boolean'],
            'notification_preferences.reviews' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        if (isset($data['notification_preferences'])) {
            $data['notification_preferences'] = array_replace((array) ($user->notification_preferences ?? []),
                array_map('boolval', array_intersect_key($data['notification_preferences'], array_flip(['email', 'listing_updates', 'reviews']))));
        }
        $user->fill($data)->save();

        return new UserResource($user);
    }

    public function password(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $request->input('password')])->save();
        // Revoke every other token after a password change.
        $user->tokens()->whereKeyNot($user->currentAccessToken()?->getKey())->delete();
        $this->audit->log('auth.password_changed', $user, [], [], ['channel' => 'api']);

        return response()->json(['message' => __('auth.password_updated')]);
    }

    public function destroy(Request $request): Response
    {
        $request->validate(['password' => ['required', 'current_password:sanctum']]);

        $user = $request->user();
        DB::transaction(function () use ($user) {
            $this->audit->log('account.deleted', $user, [], [], ['channel' => 'api']);
            $user->tokens()->delete();
            $user->delete();
        });

        return response()->noContent();
    }
}
