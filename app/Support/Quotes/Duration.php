<?php

declare(strict_types=1);

namespace App\Support\Quotes;

/** "45 min", "3 h", "2 days" — compact, localized durations for reply-time figures. */
final class Duration
{
    public static function human(int $minutes): string
    {
        if ($minutes < 60) {
            return __('quotes.duration.minutes', ['n' => format_number(max(1, $minutes))]);
        }
        if ($minutes < 48 * 60) {
            return __('quotes.duration.hours', ['n' => format_number((int) round($minutes / 60))]);
        }

        return __('quotes.duration.days', ['n' => format_number((int) round($minutes / 1440))]);
    }
}
