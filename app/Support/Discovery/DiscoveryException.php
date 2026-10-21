<?php

declare(strict_types=1);

namespace App\Support\Discovery;

use RuntimeException;

/** A page could not (or must not) be fetched; the message is a short reason code. */
final class DiscoveryException extends RuntimeException
{
    /** Errors worth retrying later (timeouts, 5xx) as opposed to permanent ones (robots, 404). */
    public function __construct(string $message, public readonly bool $retryable = false)
    {
        parent::__construct($message);
    }
}
