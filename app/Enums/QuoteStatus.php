<?php

declare(strict_types=1);

namespace App\Enums;

/** Life cycle of a quote request as the customer sees it. */
enum QuoteStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Expired = 'expired';
    case Removed = 'removed';

    public function label(): string
    {
        return __('quotes.status.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'success',
            self::Closed => 'muted',
            self::Expired => 'warning',
            self::Removed => 'danger',
        };
    }
}
