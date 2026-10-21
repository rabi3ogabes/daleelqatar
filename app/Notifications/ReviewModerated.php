<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\ModerationStatus;
use App\Models\Review;

class ReviewModerated extends DirectoryNotification
{
    public function __construct(public readonly Review $review) {}

    protected function type(): string
    {
        return 'review.'.$this->review->status->value;
    }

    protected function title(): string
    {
        return $this->review->status === ModerationStatus::Approved
            ? __('notifications.review_approved_title') : __('notifications.review_rejected_title');
    }

    protected function body(): string
    {
        $key = $this->review->status === ModerationStatus::Approved ? 'review_approved_body' : 'review_rejected_body';

        return __('notifications.'.$key, ['business' => $this->review->business->t('name')]);
    }

    protected function link(): ?array
    {
        return $this->review->status === ModerationStatus::Approved
            ? ['business.show', ['business' => $this->review->business->slug]]
            : ['account.my-reviews', []];
    }

    protected function mailEnabled(object $notifiable): bool
    {
        return false;
    }
}
