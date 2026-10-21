<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\OfferStatus;
use App\Models\Offer;

class OfferStatusChanged extends DirectoryNotification
{
    public function __construct(public readonly Offer $offer) {}

    protected function type(): string
    {
        return 'offer.'.$this->offer->status->value;
    }

    protected function title(): string
    {
        return $this->offer->status === OfferStatus::Approved ? __('notifications.offer_approved_title') : __('notifications.offer_rejected_title');
    }

    protected function body(): string
    {
        $body = __($this->offer->status === OfferStatus::Approved ? 'notifications.offer_approved_body' : 'notifications.offer_rejected_body', ['offer' => $this->offer->t('title')]);

        return $this->offer->rejection_reason ? $body.' '.__('notifications.note', ['note' => $this->offer->rejection_reason]) : $body;
    }

    protected function link(): ?array
    {
        return ['account.offers.index', []];
    }

    protected function icon(): string
    {
        return 'tag';
    }
}
