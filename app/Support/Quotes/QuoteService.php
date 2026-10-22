<?php

declare(strict_types=1);

namespace App\Support\Quotes;

use App\Enums\QuoteRecipientStatus;
use App\Enums\QuoteStatus;
use App\Models\Area;
use App\Models\Business;
use App\Models\Category;
use App\Models\Municipality;
use App\Models\QuoteRecipient;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Notifications\QuoteReplied;
use App\Notifications\QuoteRequestReceived;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Sending quote requests, answering them, closing and expiring them. */
final class QuoteService
{
    public function __construct(private readonly QuoteMatcher $matcher, private readonly AuditLogger $audit) {}

    public static function enabled(): bool
    {
        return (bool) setting('quotes.enabled', true);
    }

    public static function expiryDays(): int
    {
        return max(3, min(60, (int) setting('quotes.expiry_days', 14)));
    }

    /** Requests one person may send per day — enough for real needs, too few for spam. */
    public static function dailyLimit(): int
    {
        return max(1, (int) setting('quotes.daily_limit', 5));
    }

    /**
     * @param  array{category_id: int, municipality_id: ?int, area_id: ?int, title: string, details: string, timing: string, contact_method: string, contact_phone: ?string}  $data
     */
    public function send(User $user, array $data, ?Business $origin = null): QuoteRequest
    {
        $sentToday = QuoteRequest::query()->where('user_id', $user->id)->where('created_at', '>=', now()->subDay())->count();
        if ($sentToday >= self::dailyLimit()) {
            throw ValidationException::withMessages(['details' => __('quotes.errors.daily_limit', ['count' => self::dailyLimit()])]);
        }

        $category = Category::query()->active()->findOrFail($data['category_id']);
        $area = ! empty($data['area_id']) ? Area::query()->active()->find($data['area_id']) : null;
        $municipality = $area?->municipality ?? (! empty($data['municipality_id']) ? Municipality::query()->active()->find($data['municipality_id']) : null);

        $businesses = $this->matcher->match($category, $municipality, $area, $origin, $user->id);
        if ($businesses->isEmpty()) {
            throw ValidationException::withMessages(['category_id' => __('quotes.errors.no_recipients')]);
        }

        $request = DB::transaction(function () use ($user, $data, $category, $municipality, $area, $origin, $businesses) {
            $request = new QuoteRequest([
                'category_id' => $category->id,
                'municipality_id' => $municipality?->id,
                'area_id' => $area?->id,
                'origin_business_id' => $origin?->id,
                'title' => trim($data['title']),
                'details' => trim($data['details']),
                'timing' => $data['timing'],
                'contact_method' => $data['contact_method'],
                'contact_phone' => $data['contact_method'] === 'email' ? null : ($data['contact_phone'] ?? null),
                'locale' => app()->getLocale(),
            ]);
            $request->user()->associate($user);
            $request->status = QuoteStatus::Open;
            $request->expires_at = now()->addDays(self::expiryDays());
            $request->recipients_count = $businesses->count();
            $request->save();

            $request->recipients()->createMany($businesses->map(fn (Business $b) => [
                'business_id' => $b->id,
                'status' => QuoteRecipientStatus::Sent,
            ])->all());

            // Remember the phone number for the next request.
            if (! $user->phone && ! empty($data['contact_phone'])) {
                $user->forceFill(['phone' => $data['contact_phone']])->save();
            }

            return $request;
        });

        $request->load('recipients.business.owner');
        foreach ($request->recipients as $recipient) {
            $owner = $recipient->business?->owner;
            if ($owner) {
                $this->notify($owner, new QuoteRequestReceived($recipient));
            }
        }

        return $request;
    }

    public function markViewed(QuoteRecipient $recipient): void
    {
        if ($recipient->status === QuoteRecipientStatus::Sent) {
            $recipient->forceFill(['status' => QuoteRecipientStatus::Viewed, 'viewed_at' => now()])->save();
        }
    }

    public function reply(QuoteRecipient $recipient, string $message, ?string $priceNote): void
    {
        $request = $recipient->quoteRequest;
        $first = $recipient->status !== QuoteRecipientStatus::Replied;

        DB::transaction(function () use ($recipient, $request, $message, $priceNote, $first) {
            $recipient->forceFill([
                'status' => QuoteRecipientStatus::Replied,
                'reply' => trim($message),
                'price_note' => $priceNote !== null && trim($priceNote) !== '' ? trim($priceNote) : null,
                'replied_at' => $recipient->replied_at ?? now(),
                'viewed_at' => $recipient->viewed_at ?? now(),
                'declined_reason' => null,
            ])->save();
            if ($first) {
                $request->increment('replies_count');
            }
        });

        if ($first && $request->user) {
            $this->notify($request->user, new QuoteReplied($recipient->fresh(['business', 'quoteRequest'])));
        }
    }

    public function decline(QuoteRecipient $recipient, string $reason): void
    {
        if ($recipient->status === QuoteRecipientStatus::Replied) {
            return;
        }
        $recipient->forceFill([
            'status' => QuoteRecipientStatus::Declined,
            'declined_reason' => $reason,
            'viewed_at' => $recipient->viewed_at ?? now(),
        ])->save();
    }

    public function close(QuoteRequest $request, string $reason): void
    {
        if ($request->status !== QuoteStatus::Open) {
            return;
        }
        $request->forceFill(['status' => QuoteStatus::Closed, 'closed_at' => now(), 'closed_reason' => $reason])->save();
    }

    /** Staff removes a request (spam, abuse): businesses stop seeing it. */
    public function remove(QuoteRequest $request, User $staff): void
    {
        $old = ['status' => $request->status->value];
        $request->forceFill(['status' => QuoteStatus::Removed, 'closed_at' => now(), 'closed_reason' => 'admin'])->save();
        $this->audit->log('quote.removed', $request, $old, ['status' => QuoteStatus::Removed->value], [], $staff->id);
    }

    /** Open requests past their date become "expired" (run daily). */
    public function expireStale(): int
    {
        return QuoteRequest::query()->where('status', QuoteStatus::Open)->where('expires_at', '<=', now())
            ->update(['status' => QuoteStatus::Expired->value, 'closed_at' => now(), 'closed_reason' => 'expired', 'updated_at' => now()]);
    }

    private function notify(User $user, object $notification): void
    {
        try {
            $user->notify($notification);
        } catch (Throwable $e) {
            // A failing mail server must not undo a sent request or reply; the in-app notice is stored first.
            report($e);
        }
    }
}
