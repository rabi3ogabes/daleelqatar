<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use App\Support\Permissions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_login_and_register_pages_render_in_both_languages(): void
    {
        $this->get('/login')->assertOk()->assertSee('dir="rtl"', false)->assertSee(__('auth_ui.login_heading', [], 'ar'));
        $this->get('/en/login')->assertOk()->assertSee('dir="ltr"', false)->assertSee('Welcome back');
        $this->get('/register')->assertOk();
        $this->get('/en/forgot-password')->assertOk()->assertSee('Reset your password');
    }

    public function test_user_can_register_and_receives_verification_email(): void
    {
        Notification::fake();

        $response = $this->post('/en/register', [
            'name' => 'Mariam Al-Kuwari',
            'email' => 'Mariam@Example.com',
            'phone' => '5555 1234',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'terms' => '1',
        ]);

        $response->assertRedirect(url('/en/email/verify'));
        $user = User::query()->where('email', 'mariam@example.com')->firstOrFail();
        $this->assertSame('en', $user->locale);
        $this->assertTrue($user->hasRole(Permissions::USER));
        $this->assertNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_registration_requires_terms_and_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post('/register', [
            'name' => 'Test', 'email' => 'TAKEN@example.com', 'password' => 'Secret123', 'password_confirmation' => 'Secret123',
        ])->assertSessionHasErrors(['email', 'terms']);

        $this->assertGuest();
    }

    public function test_honeypot_blocks_bots(): void
    {
        $this->post('/register', [
            'name' => 'Bot', 'email' => 'bot@example.com', 'password' => 'Secret123', 'password_confirmation' => 'Secret123',
            'terms' => '1', 'company_website' => 'http://spam.test',
        ])->assertSessionHasErrors('company_website');
        $this->assertDatabaseMissing('users', ['email' => 'bot@example.com']);
    }

    public function test_user_can_login_and_is_audited(): void
    {
        $user = $this->makeUser(Permissions::USER, ['password' => 'Secret123']);

        $this->post('/login', ['email' => strtoupper($user->email), 'password' => 'Secret123'])
            ->assertRedirect(route('ar.account.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $user->id]);
    }

    public function test_staff_are_redirected_to_the_admin_dashboard(): void
    {
        $admin = $this->makeUser(Permissions::ADMIN, ['password' => 'Secret123']);

        $this->post('/en/login', ['email' => $admin->email, 'password' => 'Secret123'])
            ->assertRedirect(route('en.admin.dashboard'));
    }

    public function test_failed_login_is_rejected_and_logged_without_password(): void
    {
        $user = $this->makeUser(Permissions::USER, ['password' => 'Secret123']);

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertGuest();
        $log = AuditLog::query()->where('action', 'auth.login_failed')->firstOrFail();
        $this->assertStringNotContainsString('wrong-password', json_encode($log->toArray()));
    }

    public function test_login_is_rate_limited(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'nope'.$i]);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'nope'])->assertStatus(429);
    }

    public function test_suspended_user_cannot_login(): void
    {
        $user = User::factory()->suspended()->create(['password' => 'Secret123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'Secret123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_suspended_user_is_signed_out_on_next_request(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->get('/account')->assertOk();

        $user->forceFill(['status' => 'suspended'])->save();
        $this->actingAs($user->fresh())->get('/account')->assertRedirect(route('ar.login'));
    }

    public function test_account_area_requires_authentication_and_verified_email(): void
    {
        $this->get('/account')->assertRedirect(route('ar.login'));

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->get('/account')->assertRedirect(route('ar.verification.notice'));
        $this->actingAs($unverified)->get('/email/verify')->assertOk();
    }

    public function test_email_can_be_verified_with_signed_link(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('ar.verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);

        $this->actingAs($user)->get($url)->assertRedirect(route('ar.account.dashboard'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_link_with_wrong_hash_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('ar.verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1('other@example.com')]);

        $this->actingAs($user)->get($url)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_password_can_be_reset(): void
    {
        Notification::fake();
        $user = $this->makeUser(Permissions::USER, ['password' => 'OldSecret1']);

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->get('/reset-password/'.$token.'?email='.$user->email)->assertOk();
        $this->post('/reset-password', [
            'token' => $token, 'email' => $user->email, 'password' => 'NewSecret1', 'password_confirmation' => 'NewSecret1',
        ])->assertRedirect(route('ar.login'));

        $this->post('/login', ['email' => $user->email, 'password' => 'NewSecret1'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_forgot_password_does_not_reveal_unknown_emails(): void
    {
        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertSessionHas('status')->assertSessionHasNoErrors();
    }

    public function test_user_can_logout(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->post('/logout')->assertRedirect(route('ar.home'));
        $this->assertGuest();
    }
}
