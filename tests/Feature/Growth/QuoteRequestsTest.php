<?php

namespace Tests\Feature\Growth;

use App\Enums\QuoteRecipientStatus;
use App\Enums\QuoteStatus;
use App\Models\Business;
use App\Models\Category;
use App\Models\Municipality;
use App\Models\QuoteRecipient;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Notifications\QuoteReplied;
use App\Notifications\QuoteRequestReceived;
use App\Services\SettingsService;
use App\Support\Permissions;
use App\Support\Quotes\QuoteService;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class QuoteRequestsTest extends TestCase
{
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::query()->whereNotNull('parent_id')->firstOrFail();
    }

    private function listing(?User $owner = null, array $attributes = []): Business
    {
        return Business::factory()->published()->create(array_merge([
            'owner_id' => ($owner ?? $this->makeUser(Permissions::BUSINESS_OWNER))->id,
            'category_id' => $this->category->id,
            'is_demo' => false,
        ], $attributes));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => $this->category->id,
            'title' => 'Service for two split AC units',
            'details' => 'Two 2-ton split units in a villa need cleaning and a gas check this week.',
            'timing' => 'this_week',
            'contact_method' => 'whatsapp',
            'contact_phone' => '+974 5555 1234',
            'consent' => '1',
        ], $overrides);
    }

    public function test_form_is_public_and_remembers_where_to_return_after_sign_in(): void
    {
        $this->get('/en/quotes/new?category='.$this->category->slug)
            ->assertOk()->assertSee(__('quotes.page_title', [], 'en'))->assertSee(__('quotes.sign_in_to_send', [], 'en'));

        $this->assertStringContainsString('/en/quotes/new', (string) session('url.intended'));
    }

    public function test_sending_requires_a_verified_account(): void
    {
        $this->post('/en/quotes', $this->payload())->assertRedirect();
        $this->assertSame(0, QuoteRequest::query()->count());
    }

    public function test_preview_counts_only_businesses_that_can_reply(): void
    {
        $this->listing();
        $this->listing();
        Business::factory()->published()->create(['category_id' => $this->category->id, 'owner_id' => null]); // nobody to answer
        $this->listing($this->makeUser(Permissions::BUSINESS_OWNER, ['status' => 'suspended']));

        $this->getJson('/en/quotes/preview?category_id='.$this->category->id)->assertOk()->assertJson(['count' => 2]);
    }

    public function test_request_reaches_matching_owners_closest_first_and_never_the_sender(): void
    {
        Notification::fake();
        app(SettingsService::class)->set('quotes.max_recipients', 3);
        [$doha, $other] = Municipality::query()->orderBy('id')->take(2)->get()->all();

        $customer = $this->makeUser();
        $near = $this->listing(null, ['municipality_id' => $doha->id]);
        $far1 = $this->listing(null, ['municipality_id' => $other->id]);
        $far2 = $this->listing(null, ['municipality_id' => $other->id]);
        $far3 = $this->listing(null, ['municipality_id' => $other->id]);
        $own = $this->listing($customer, ['municipality_id' => $doha->id]);

        $this->actingAs($customer)->post('/en/quotes', $this->payload(['municipality_id' => $doha->id]))->assertRedirect();

        $quote = QuoteRequest::query()->sole();
        $ids = $quote->recipients()->pluck('business_id')->all();
        $this->assertCount(3, $ids);
        $this->assertSame($near->id, $ids[0]);
        $this->assertNotContains($own->id, $ids);
        $this->assertSame(QuoteStatus::Open, $quote->status);
        $this->assertSame(3, $quote->recipients_count);
        Notification::assertSentTo($near->owner, QuoteRequestReceived::class);
        Notification::assertNotSentTo($customer, QuoteRequestReceived::class);
        $this->assertSame('+974 5555 1234', $customer->fresh()->phone);
    }

    public function test_request_started_from_a_business_page_includes_that_business(): void
    {
        $origin = $this->listing();
        foreach (range(1, 6) as $i) {
            $this->listing();
        }

        $this->actingAs($this->makeUser())->post('/en/quotes', $this->payload(['business' => $origin->slug]))->assertRedirect();

        $this->assertSame($origin->id, QuoteRequest::query()->sole()->recipients()->value('business_id'));
    }

    public function test_no_recipients_and_daily_limit_are_explained(): void
    {
        $customer = $this->makeUser();
        $this->actingAs($customer)->post('/en/quotes', $this->payload())->assertSessionHasErrors('category_id');

        $this->listing();
        app(SettingsService::class)->set('quotes.daily_limit', 1);
        $this->actingAs($customer)->post('/en/quotes', $this->payload())->assertSessionHasNoErrors();
        $this->actingAs($customer)->post('/en/quotes', $this->payload())->assertSessionHasErrors('details');
    }

    public function test_owner_reads_and_replies_and_customer_sees_the_reply(): void
    {
        Notification::fake();
        $owner = $this->makeUser(Permissions::BUSINESS_OWNER);
        $business = $this->listing($owner);
        $customer = $this->makeUser();
        $this->actingAs($customer)->post('/en/quotes', $this->payload());
        $lead = QuoteRecipient::query()->sole();

        $this->actingAs($owner)->get('/en/account/leads')->assertOk()->assertSee('Service for two split AC units');
        $this->actingAs($owner)->get('/en/account/leads/'.$lead->id)->assertOk()->assertSee('+974 5555 1234');
        $this->assertSame(QuoteRecipientStatus::Viewed, $lead->fresh()->status);

        $this->actingAs($owner)->post('/en/account/leads/'.$lead->id.'/reply', ['reply' => 'We can come on Thursday morning.', 'price_note' => 'QAR 300 for both'])
            ->assertRedirect();
        $this->assertSame(QuoteRecipientStatus::Replied, $lead->fresh()->status);
        $this->assertSame(1, $lead->quoteRequest->fresh()->replies_count);
        Notification::assertSentTo($customer, QuoteReplied::class);

        $this->actingAs($customer)->get('/en/account/quotes/'.$lead->quoteRequest->uuid)->assertOk()
            ->assertSee('We can come on Thursday morning.')->assertSee('QAR 300 for both')->assertSee($business->name_en);

        // Editing the reply does not count it twice.
        $this->actingAs($owner)->post('/en/account/leads/'.$lead->id.'/reply', ['reply' => 'Updated: Thursday 9am works.']);
        $this->assertSame(1, $lead->quoteRequest->fresh()->replies_count);
    }

    public function test_others_cannot_see_a_lead_or_a_request(): void
    {
        $this->listing();
        $customer = $this->makeUser();
        $this->actingAs($customer)->post('/en/quotes', $this->payload());
        $lead = QuoteRecipient::query()->sole();
        $stranger = $this->makeUser(Permissions::BUSINESS_OWNER);

        $this->actingAs($stranger)->get('/en/account/leads/'.$lead->id)->assertNotFound();
        $this->actingAs($stranger)->post('/en/account/leads/'.$lead->id.'/reply', ['reply' => 'Hello there, we can help.'])->assertNotFound();
        $this->actingAs($stranger)->get('/en/account/quotes/'.$lead->quoteRequest->uuid)->assertNotFound();
    }

    public function test_decline_close_and_expiry(): void
    {
        $owner = $this->makeUser(Permissions::BUSINESS_OWNER);
        $this->listing($owner);
        $customer = $this->makeUser();
        $this->actingAs($customer)->post('/en/quotes', $this->payload());
        $lead = QuoteRecipient::query()->sole();

        $this->actingAs($owner)->post('/en/account/leads/'.$lead->id.'/decline', ['reason' => 'outside_area'])->assertRedirect();
        $this->assertSame(QuoteRecipientStatus::Declined, $lead->fresh()->status);

        $quote = $lead->quoteRequest;
        $this->actingAs($customer)->post('/en/account/quotes/'.$quote->uuid.'/close', ['reason' => 'found'])->assertRedirect();
        $this->assertSame(QuoteStatus::Closed, $quote->fresh()->status);

        // A closed request takes no more replies.
        $this->actingAs($owner)->post('/en/account/leads/'.$lead->id.'/reply', ['reply' => 'Too late but here is a reply.'])->assertForbidden();

        $this->listing();
        $this->actingAs($customer)->post('/en/quotes', $this->payload());
        $open = QuoteRequest::query()->where('status', 'open')->sole();
        $open->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->assertSame(1, app(QuoteService::class)->expireStale());
        $this->assertSame(QuoteStatus::Expired, $open->fresh()->status);
    }

    public function test_staff_see_stats_and_can_remove_abuse(): void
    {
        $this->listing();
        $this->actingAs($this->makeUser())->post('/en/quotes', $this->payload());
        $quote = QuoteRequest::query()->sole();
        $moderator = $this->makeUser(Permissions::MODERATOR);

        $this->actingAs($moderator)->get('/en/admin/quotes')->assertOk()->assertSee('Service for two split AC units');
        $this->actingAs($moderator)->post('/en/admin/quotes/'.$quote->uuid.'/remove')->assertRedirect();
        $this->assertSame(QuoteStatus::Removed, $quote->fresh()->status);

        $this->actingAs($this->makeUser())->get('/en/admin/quotes')->assertForbidden();
    }

    public function test_business_and_category_pages_offer_quotes(): void
    {
        $business = $this->listing();

        $this->get('/en/business/'.$business->slug)->assertOk()->assertSee('/en/quotes/new?business='.$business->slug, false);
        $this->get('/en/categories/'.$this->category->slug)->assertOk()->assertSee('/en/quotes/new?category='.$this->category->slug, false);
    }
}
