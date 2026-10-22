<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\QuoteRecipient;

/** To a business owner: a customer asked for a quote. */
class QuoteRequestReceived extends DirectoryNotification
{
    public function __construct(public readonly QuoteRecipient $recipient) {}

    protected function type(): string
    {
        return 'quote.received';
    }

    protected function title(): string
    {
        return __('quotes.notify.received_title');
    }

    protected function body(): string
    {
        $request = $this->recipient->quoteRequest;

        return __('quotes.notify.received_body', [
            'business' => $this->recipient->business?->t('name'),
            'title' => $request->title,
            'location' => $request->locationLabel() ?? __('quotes.anywhere'),
        ]);
    }

    protected function link(): ?array
    {
        return ['account.leads.show', [$this->recipient->id]];
    }

    protected function mailEnabled(object $notifiable): bool
    {
        return ! method_exists($notifiable, 'wantsNotification') || $notifiable->wantsNotification('quotes');
    }

    protected function icon(): string
    {
        return 'message-square-quote';
    }
}
