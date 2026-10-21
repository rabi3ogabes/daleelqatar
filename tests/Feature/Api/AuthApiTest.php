<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;

class AuthApiTest extends ApiTestCase
{
    public function test_register_returns_201_with_token_and_sends_verification(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Mariam Al-Kuwari',
            'email' => 'Mariam@Example.com',
            'password' => 'secret-pass-123',
            'password_confirmation' => 'secret-pass-123',
            'device_name' => 'iPhone 16',
            'locale' => 'en',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'mariam@example.com')
            ->assertJsonPath('data.user.email_verified', false)
            ->assertJsonPath('data.user.roles.0', 'user');
        $this->assertNotEmpty($response->json('data.token'));

        $user = User::query()->where('email', 'mariam@example.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_register_rejects_duplicate_email_case_insensitively(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Someone', 'email' => 'TAKEN@example.com', 'password' => 'secret-pass-123',
            'password_confirmation' => 'secret-pass-123', 'device_name' => 'test',
        ])->assertStatus(422)->assertJsonPath('code', 'validation_failed')->assertJsonValidationErrors('email');
    }

    public function test_login_success_and_wrong_password(): void
    {
        User::factory()->create(['email' => 'owner@example.com', 'password' => 'correct-horse-1']);

        $this->postJson('/api/v1/auth/login', ['email' => 'owner@example.com', 'password' => 'correct-horse-1', 'device_name' => 'pixel'])
            ->assertOk()->assertJsonStructure(['data' => ['user' => ['id', 'email'], 'token', 'token_type', 'expires_at']]);

        $this->postJson('/api/v1/auth/login', ['email' => 'owner@example.com', 'password' => 'wrong', 'device_name' => 'pixel'])
            ->assertStatus(401)->assertJsonPath('code', 'invalid_credentials');

        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_failed']);
    }

    public function test_suspended_user_cannot_log_in(): void
    {
        User::factory()->suspended()->create(['email' => 'blocked@example.com', 'password' => 'correct-horse-1']);

        $this->postJson('/api/v1/auth/login', ['email' => 'blocked@example.com', 'password' => 'correct-horse-1', 'device_name' => 'x'])
            ->assertStatus(403)->assertJsonPath('code', 'account_suspended');
    }

    public function test_me_requires_token(): void
    {
        $this->getJson('/api/v1/me')->assertStatus(401)->assertJsonPath('code', 'unauthenticated');
    }

    public function test_me_with_token_and_logout_revokes_it(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('device')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $user->uuid);
        $this->resetAuth();

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertSame(0, $user->tokens()->count());
        $this->resetAuth();

        $this->withToken($token)->getJson('/api/v1/me')->assertStatus(401);
    }

    public function test_suspended_account_token_is_refused(): void
    {
        $user = User::factory()->suspended()->create();
        $token = $user->createToken('device')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/me')->assertStatus(403)->assertJsonPath('code', 'account_suspended');
    }

    public function test_profile_update_and_password_change(): void
    {
        $user = User::factory()->create(['password' => 'old-password-1']);
        $token = $user->createToken('device')->plainTextToken;

        $this->withToken($token)->patchJson('/api/v1/me', ['name' => 'New Name', 'locale' => 'en', 'notification_preferences' => ['email' => false]])
            ->assertOk()->assertJsonPath('data.name', 'New Name')->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.notification_preferences.email', false);

        $this->withToken($token)->putJson('/api/v1/me/password', ['current_password' => 'nope', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
            ->assertStatus(422);
        $this->withToken($token)->putJson('/api/v1/me/password', ['current_password' => 'old-password-1', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
            ->assertOk();
    }

    public function test_forgot_password_does_not_reveal_accounts(): void
    {
        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => User::factory()->create()->email])->assertOk()->json('message');
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk()->json('message');

        $this->assertSame($known, $unknown);
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'target@example.com']);

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'target@example.com', 'password' => 'bad', 'device_name' => 'x'])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'target@example.com', 'password' => 'bad', 'device_name' => 'x'])
            ->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
    }

    public function test_arabic_is_default_and_english_is_negotiated(): void
    {
        // No language preference → Arabic.
        $this->getJson('/api/v1/me', ['Accept-Language' => ''])->assertHeader('Content-Language', 'ar')
            ->assertJsonPath('message', 'يلزم تسجيل الدخول.');
        $this->getJson('/api/v1/me', ['Accept-Language' => 'en-US,en;q=0.9'])->assertHeader('Content-Language', 'en')
            ->assertJsonPath('message', 'Unauthenticated.');
    }
}
