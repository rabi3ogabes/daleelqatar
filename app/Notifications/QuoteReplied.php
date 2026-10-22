<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\QuoteRecipient;

/** To the customer: a business answered their quote request. */
class QuoteReplied extends DirectoryNotification
{
    public function __construct(public readonly QuoteRecipient $recipient) {}

    protected function type(): string
    {
        return 'quote.replied';
    }

    protected function title(): string
    {
        return __('quotes.notify.replied_title');
    }

    protected function body(): string
    {
        return __('quotes.notify.replied_body', [
            'business' => $this->recipient->business?->t('name'),
            'title' => $this->recipient->quoteRequest->title,
        ]);
    }

    protected function link(): ?array
    {
        return ['account.quotes.show', [$this->recipient->quoteRequest->uuid]];
    }

    protected function mailEnabled(object $notifiable): bool
    {
        return ! method_exists($notifiable, 'wantsNotification') || $notifiable->wantsNotification('quotes');
    }

    protected function icon(): string
    {
        return 'reply';
    }
}
