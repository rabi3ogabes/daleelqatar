<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Review;

class NewReviewReceived extends DirectoryNotification
{
    public function __construct(public readonly Review $review) {}

    protected function type(): string
    {
        return 'review.received';
    }

    protected function title(): string
    {
        return __('notifications.review_received_title');
    }

    protected function body(): string
    {
        return __('notifications.review_received_body', ['business' => $this->review->business->t('name'), 'rating' => $this->review->rating]);
    }

    protected function link(): ?array
    {
        return ['account.reviews.index', []];
    }

    protected function icon(): string
    {
        return 'star';
    }
}
