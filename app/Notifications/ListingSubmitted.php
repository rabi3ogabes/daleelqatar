<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Business;

class ListingSubmitted extends DirectoryNotification
{
    public function __construct(public readonly Business $business) {}

    protected function type(): string
    {
        return 'listing.submitted';
    }

    protected function title(): string
    {
        return __('notifications.listing_submitted_title');
    }

    protected function body(): string
    {
        return __('notifications.listing_submitted_body', ['business' => $this->business->t('name')]);
    }

    protected function link(): ?array
    {
        return ['admin.businesses.show', ['business' => $this->business->id]];
    }

    protected function icon(): string
    {
        return 'inbox';
    }
}
