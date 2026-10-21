<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class GuestEmailVerificationTest extends TestCase
{
    public function test_signed_link_verifies_without_a_session_for_mobile_users(): void
    {
        $user = $this->makeUser(attributes: ['email_verified_at' => null]);
        $url = URL::temporarySignedRoute('en.verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);

        $this->get($url)->assertRedirect(url('/en/login'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_tampered_or_mismatched_links_are_rejected(): void
    {
        $user = $this->makeUser(attributes: ['email_verified_at' => null]);
        $good = URL::temporarySignedRoute('en.verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);

        $this->get($good.'x')->assertForbidden();
        $this->actingAs($this->makeUser())->get($good)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
