<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The time left for the current request. Serverless functions are stopped
 * after 60 seconds, so long jobs (enrichment, category checks) set a deadline
 * and every slow call (Google, company websites, the AI) caps its own timeout
 * to what is left — finishing early and cleanly instead of being cut off.
 *
 * Without a deadline nothing is limited (console commands, the crawler).
 */
final class Deadline
{
    private static ?float $until = null;

    public static function start(float $seconds): void
    {
        self::$until = microtime(true) + $seconds;
    }

    public static function clear(): void
    {
        self::$until = null;
    }

    public static function active(): bool
    {
        return self::$until !== null;
    }

    /** Seconds left (INF without a deadline). */
    public static function remaining(): float
    {
        return self::$until === null ? INF : max(0.0, self::$until - microtime(true));
    }

    /** Whether at least $seconds are left. */
    public static function has(float $seconds): bool
    {
        return self::remaining() >= $seconds;
    }

    /** A timeout no longer than $preferred and that still ends $margin seconds before the deadline. */
    public static function timeout(float $preferred, float $margin = 2.0, float $minimum = 1.0): float
    {
        return max($minimum, min($preferred, self::remaining() - $margin));
    }
}
