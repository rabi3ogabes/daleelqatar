<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Business;

class ListingStatusChanged extends DirectoryNotification
{
    public function __construct(public readonly Business $business, public readonly string $event, public readonly ?string $note = null) {}

    protected function type(): string
    {
        return 'listing.'.$this->event;
    }

    protected function title(): string
    {
        return __('notifications.listing_'.$this->event.'_title');
    }

    protected function body(): string
    {
        $body = __('notifications.listing_'.$this->event.'_body', ['business' => $this->business->t('name')]);

        return $this->note ? $body.' '.__('notifications.note', ['note' => $this->note]) : $body;
    }

    protected function link(): ?array
    {
        return ['account.businesses.show', ['business' => $this->business->uuid]];
    }

    protected function icon(): string
    {
        return $this->event === 'approved' ? 'badge-check' : 'alert-circle';
    }
}
