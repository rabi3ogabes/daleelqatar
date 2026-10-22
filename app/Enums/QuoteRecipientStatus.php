<?php

declare(strict_types=1);

namespace App\Enums;

/** Where one business stands with one quote request. */
enum QuoteRecipientStatus: string
{
    case Sent = 'sent';
    case Viewed = 'viewed';
    case Replied = 'replied';
    case Declined = 'declined';

    public function label(): string
    {
        return __('quotes.recipient_status.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Sent => 'info',
            self::Viewed => 'warning',
            self::Replied => 'success',
            self::Declined => 'muted',
        };
    }

    public function isAnswered(): bool
    {
        return $this === self::Replied || $this === self::Declined;
    }
}
