<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportReason: string
{
    case IncorrectInfo = 'incorrect_info';
    case ClosedPermanently = 'closed';
    case Duplicate = 'duplicate';
    case Spam = 'spam';
    case Offensive = 'offensive';
    case Fraud = 'fraud';
    case Other = 'other';

    public function label(): string
    {
        return __('status.reason.'.$this->value);
    }

    /** Reasons relevant when reporting a review rather than a listing. */
    public static function forReviews(): array
    {
        return [self::Spam, self::Offensive, self::Fraud, self::IncorrectInfo, self::Other];
    }
}
