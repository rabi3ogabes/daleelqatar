<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ModerationStatus;
use App\Enums\ReportReason;
use App\Exceptions\WorkflowException;
use App\Models\Business;
use App\Models\Review;
use App\Models\ReviewReport;
use App\Models\User;
use App\Notifications\NewReviewReceived;
use App\Notifications\ReviewModerated;
use Illuminate\Support\Facades\DB;

class ReviewService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function enabled(): bool
    {
        return $this->settings->bool('reviews.enabled');
    }

    public function submit(Business $business, User $user, array $data): Review
    {
        if (! $this->enabled()) {
            throw new WorkflowException(__('reviews.disabled'));
        }
        if (! $business->isPublished()) {
            throw new WorkflowException(__('reviews.not_available'));
        }
        if ($business->isOwnedBy($user)) {
            throw new WorkflowException(__('reviews.own_business'));
        }
        if (Review::withTrashed()->where('business_id', $business->id)->where('user_id', $user->id)->exists()) {
            throw new WorkflowException(__('reviews.already_reviewed'));
        }

        return DB::transaction(function () use ($business, $user, $data) {
            $review = new Review([
                'rating' => (int) $data['rating'],
                'title' => $data['title'] ?? null,
                'body' => $data['body'],
                'locale' => app()->getLocale(),
            ]);
            $review->business_id = $business->id;
            $review->user_id = $user->id;
            $review->status = $this->settings->bool('reviews.require_moderation') ? ModerationStatus::Pending : ModerationStatus::Approved;
            $review->save();

            if ($review->status === ModerationStatus::Approved) {
                $this->refreshRating($business);
                $this->notifyOwner($review);
            }

            return $review;
        });
    }

    public function moderate(Review $review, User $moderator, ModerationStatus $status, ?string $note = null): Review
    {
        return DB::transaction(function () use ($review, $moderator, $status, $note) {
            $from = $review->status;
            $review->status = $status;
            $review->moderated_by = $moderator->id;
            $review->moderated_at = now();
            $review->moderation_note = $note;
            $review->save();

            $this->refreshRating($review->business);
            $this->audit->log('review.'.$status->value, $review, ['status' => $from->value], ['status' => $status->value], array_filter(['note' => $note]), $moderator->id);

            if ($status === ModerationStatus::Approved && $from !== ModerationStatus::Approved) {
                $this->notifyOwner($review);
            }
            if (in_array($status, [ModerationStatus::Approved, ModerationStatus::Rejected], true) && $from === ModerationStatus::Pending) {
                $review->user?->notify(new ReviewModerated($review));
            }

            return $review;
        });
    }

    public function reply(Review $review, User $owner, ?string $reply): Review
    {
        if (! $this->settings->bool('reviews.owner_replies')) {
            throw new WorkflowException(__('reviews.replies_disabled'));
        }
        if (! $review->business->isOwnedBy($owner)) {
            throw new WorkflowException(__('auth.unauthorized'));
        }
        if ($review->status !== ModerationStatus::Approved) {
            throw new WorkflowException(__('reviews.reply_only_approved'));
        }

        $review->owner_reply = $reply ?: null;
        $review->owner_replied_at = $reply ? now() : null;
        $review->save();

        return $review;
    }

    public function report(Review $review, ?User $user, ReportReason $reason, ?string $details): ReviewReport
    {
        if ($user && ReviewReport::query()->where('review_id', $review->id)->where('user_id', $user->id)->exists()) {
            throw new WorkflowException(__('reviews.already_reported'));
        }

        return DB::transaction(function () use ($review, $user, $reason, $details) {
            $report = new ReviewReport(['reason' => $reason, 'details' => $details]);
            $report->review_id = $review->id;
            $report->user_id = $user?->id;
            $report->status = 'open';
            $report->save();
            $review->increment('reports_count');

            return $report;
        });
    }

    public function refreshRating(Business $business): void
    {
        $stats = Review::query()->where('business_id', $business->id)->where('status', ModerationStatus::Approved)
            ->selectRaw('count(*) as c, avg(rating) as a')->first();

        $business->forceFill([
            'reviews_count' => (int) $stats->c,
            'rating_avg' => $stats->c > 0 ? round((float) $stats->a, 2) : null,
        ])->saveQuietly();
    }

    private function notifyOwner(Review $review): void
    {
        $owner = $review->business?->owner;
        if ($owner && $owner->id !== $review->user_id && $owner->wantsNotification('reviews')) {
            $owner->notify(new NewReviewReceived($review));
        }
    }
}
