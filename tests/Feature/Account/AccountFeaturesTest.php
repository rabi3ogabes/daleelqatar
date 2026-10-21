<?php

namespace Tests\Feature\Account;

use App\Enums\BusinessStatus;
use App\Enums\ModerationStatus;
use App\Enums\OfferStatus;
use App\Models\Business;
use App\Models\BusinessClaimRequest;
use App\Models\Offer;
use App\Models\Review;
use App\Models\User;
use App\Notifications\StaffAlert;
use App\Services\ReviewService;
use App\Services\SettingsService;
use App\Support\Permissions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountFeaturesTest extends TestCase
{
    private function published(User $owner): Business
    {
        return Business::factory()->published()->create(['owner_id' => $owner->id, 'is_demo' => false]);
    }

    private function offerPayload(Business $business, array $overrides = []): array
    {
        return array_merge([
            'business_id' => $business->uuid,
            'title_ar' => 'خصم الصيف',
            'title_en' => 'Summer discount',
            'discount_label_ar' => '20%',
            'discount_label_en' => '20% off',
            'starts_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDays(10)->format('Y-m-d\TH:i'),
            'is_active' => '1',
            'intent' => 'submit',
        ], $overrides);
    }

    public function test_owner_offer_is_created_pending_moderation(): void
    {
        $owner = $this->makeUser(Permissions::BUSINESS_OWNER);
        $business = $this->published($owner);

        $this->actingAs($owner)->get('/account/offers/create')->assertOk();
        $this->actingAs($owner)->post('/account/offers', $this->offerPayload($business))->assertRedirect(route('ar.account.offers.index'));

        $offer = Offer::query()->firstOrFail();
        $this->assertSame(OfferStatus::Pending, $offer->status);
        $this->get('/en/offers')->assertOk()->assertDontSee('Summer discount');

        // Drafts stay drafts
        $this->actingAs($owner)->post('/account/offers', $this->offerPayload($business, ['title_en' => 'Draft deal', 'intent' => 'draft']));
        $this->assertSame(OfferStatus::Draft, Offer::query()->where('title_en', 'Draft deal')->value('status'));
    }

    public function test_offers_require_an_approved_owned_business(): void
    {
        $owner = $this->makeUser(Permissions::BUSINESS_OWNER);
        $draft = Business::factory()->create(['owner_id' => $owner->id]);
        $foreign = $this->published($this->makeUser(Permissions::BUSINESS_OWNER));

        $this->actingAs($owner)->post('/account/offers', $this->offerPayload($draft))->assertForbidden();
        $this->actingAs($owner)->post('/account/offers', $this->offerPayload($foreign))->assertNotFound();
        $this->assertSame(0, Offer::query()->count());
    }

    public function test_expired_offers_are_excluded_from_public_listings(): void
    {
        $owner = $this->makeUser(Permissions::BUSINESS_OWNER);
        $business = $this->published($owner);
        Offer::factory()->create(['business_id' => $business->id, 'title_en' => 'Live deal']);
        Offer::factory()->create(['business_id' => $business->id, 'title_en' => 'Old deal', 'starts_at' => now()->subMonth(), 'ends_at' => now()->subDay()]);

        $this->assertSame(['Live deal'], Offer::query()->live()->pluck('title_en')->all());
        $this->get('/en/offers')->assertOk()->assertSee('Live deal')->assertDontSee('Old deal');
        $this->actingAs($owner)->get('/en/account/offers')->assertOk()->assertSee('Old deal')->assertSee('Expired');
    }

    public function test_owner_cannot_edit_another_owners_offer(): void
    {
        $owner = $this->makeUser(Permissions::BUSINESS_OWNER);
        $offer = Offer::factory()->create(['business_id' => $this->published($this->makeUser(Permissions::BUSINESS_OWNER))->id]);

        $this->actingAs($owner)->get('/account/offers/'.$offer->uuid.'/edit')->assertNotFound();
        $this->actingAs($owner)->delete('/account/offers/'.$offer->uuid)->assertNotFound();
        $this->assertNotNull($offer->fresh());
    }

    public function test_owner_can_reply_to_reviews_of_own_business_only(): void
    {
        $owner = $this->makeUser(Permissions::BUSINESS_OWNER);
        $business = $this->published($owner);
        $customer = $this->makeUser();
        $review = app(ReviewService::class)->submit($business, $customer, ['rating' => 5, 'body' => 'Excellent coffee and friendly staff.']);
        app(ReviewService::class)->moderate($review, $this->makeUser(Permissions::MODERATOR), ModerationStatus::Approved);

        $this->actingAs($owner)->get('/en/account/reviews')->assertOk()->assertSee('Excellent coffee');
        $this->actingAs($owner)->post('/account/reviews/'.$review->uuid.'/reply', ['owner_reply' => 'Thank you for visiting!'])->assertSessionHas('success');
        $this->assertSame('Thank you for visiting!', $review->fresh()->owner_reply);

        $stranger = $this->makeUser(Permissions::BUSINESS_OWNER);
        $this->actingAs($stranger)->post('/account/reviews/'.$review->uuid.'/reply', ['owner_reply' => 'Fake'])->assertNotFound();
        $this->assertSame('Thank you for visiting!', $review->fresh()->owner_reply);
    }

    public function test_owner_replies_respect_the_setting(): void
    {
        app(SettingsService::class)->set('reviews.owner_replies', false);
        $owner = $this->makeUser(Permissions::BUSINESS_OWNER);
        $business = $this->published($owner);
        $review = app(ReviewService::class)->submit($business, $this->makeUser(), ['rating' => 4, 'body' => 'Very good place to work from.']);
        app(ReviewService::class)->moderate($review, $this->makeUser(Permissions::MODERATOR), ModerationStatus::Approved);

        $this->actingAs($owner)->post('/account/reviews/'.$review->uuid.'/reply', ['owner_reply' => 'Hi'])->assertSessionHas('error');
        $this->assertNull($review->fresh()->owner_reply);
    }

    public function test_user_can_delete_own_review_and_rating_is_recalculated(): void
    {
        $business = $this->published($this->makeUser(Permissions::BUSINESS_OWNER));
        $author = $this->makeUser();
        $review = app(ReviewService::class)->submit($business, $author, ['rating' => 2, 'body' => 'Service was slow on my visit.']);
        app(ReviewService::class)->moderate($review, $this->makeUser(Permissions::MODERATOR), ModerationStatus::Approved);
        $this->assertSame(1, $business->fresh()->reviews_count);

        $this->actingAs($author)->get('/account/my-reviews')->assertOk();
        $this->actingAs($this->makeUser())->delete('/account/my-reviews/'.$review->uuid)->assertNotFound();
        $this->actingAs($author)->delete('/account/my-reviews/'.$review->uuid)->assertRedirect(route('ar.account.my-reviews'));

        $this->assertSoftDeleted($review);
        $this->assertSame(0, $business->fresh()->reviews_count);
        $this->assertNull($business->fresh()->rating_avg);
    }

    public function test_favorites_claims_and_notifications_pages(): void
    {
        $user = $this->makeUser();
        $business = Business::factory()->published()->create(['name_en' => 'Saved Place']);
        $user->favorites()->attach($business->id);
        $claim = new BusinessClaimRequest(['claimant_name' => 'X', 'phone' => '+97455555555', 'email' => 'x@example.com']);
        $claim->business_id = $business->id;
        $claim->user_id = $user->id;
        $claim->status = 'pending';
        $claim->save();
        $user->notify(new StaffAlert('report', 'Hello there'));

        $this->actingAs($user)->get('/en/account/favorites')->assertOk()->assertSee('Saved Place');
        $this->actingAs($user)->get('/en/account/claims')->assertOk()->assertSee('Saved Place');
        $this->actingAs($user)->post('/account/claims/'.$claim->uuid.'/cancel')->assertSessionHas('success');
        $this->assertSame('cancelled', $claim->fresh()->status->value);

        $this->actingAs($user)->get('/en/account/notifications')->assertOk()->assertSee('Hello there');
        $this->actingAs($user)->post('/account/notifications/read-all');
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_settings_profile_password_and_preferences(): void
    {
        $user = $this->makeUser(Permissions::USER, ['password' => 'OldSecret1']);

        $this->actingAs($user)->get('/account/settings')->assertOk();
        $this->actingAs($user)->put('/account/settings/profile', ['name' => 'New Name', 'email' => $user->email, 'phone' => '+974 5555 0000', 'locale' => 'en'])
            ->assertSessionHas('success');
        $this->assertSame('New Name', $user->fresh()->name);
        $this->assertSame('en', $user->fresh()->locale);

        $this->actingAs($user)->put('/account/settings/password', ['current_password' => 'wrong', 'password' => 'NewSecret1', 'password_confirmation' => 'NewSecret1'])
            ->assertSessionHasErrors('current_password');
        $this->actingAs($user)->put('/account/settings/password', ['current_password' => 'OldSecret1', 'password' => 'NewSecret1', 'password_confirmation' => 'NewSecret1'])
            ->assertSessionHas('success');
        $this->assertTrue(Hash::check('NewSecret1', $user->fresh()->password));

        $this->actingAs($user)->put('/account/settings/preferences', ['prefs' => ['email' => '0', 'listing_updates' => '1', 'reviews' => '0']]);
        $this->assertFalse($user->fresh()->wantsNotification('email'));
        $this->assertTrue($user->fresh()->wantsNotification('listing_updates'));
    }

    public function test_changing_email_requires_reverification(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->put('/account/settings/profile', ['name' => $user->name, 'email' => 'new@example.com', 'locale' => 'ar'])
            ->assertRedirect(route('ar.verification.notice'));
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_account_deletion_requires_password(): void
    {
        $user = $this->makeUser(Permissions::BUSINESS_OWNER, ['password' => 'Secret123']);
        $business = $this->published($user);

        $this->actingAs($user)->delete('/account/settings', ['delete_password' => 'wrong'])->assertSessionHasErrorsIn('deleteAccount', 'delete_password');
        $this->assertNotSoftDeleted($user);

        $this->actingAs($user)->delete('/account/settings', ['delete_password' => 'Secret123'])->assertRedirect(route('ar.home'));
        $this->assertSoftDeleted($user);
        $this->assertGuest();
        $this->assertNull($business->fresh()->owner_id);
    }
}
