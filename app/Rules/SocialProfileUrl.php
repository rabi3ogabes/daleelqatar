<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\SocialPlatform;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Accepts only https URLs on the official host(s) of the given social platform. */
class SocialProfileUrl implements ValidationRule
{
    public function __construct(private readonly SocialPlatform $platform) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $host = strtolower((string) parse_url((string) $value, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url((string) $value, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true) || ! in_array($host, $this->platform->hosts(), true)) {
            $fail(__('validation.social_url', ['platform' => $this->platform->label()]));
        }
    }
}
