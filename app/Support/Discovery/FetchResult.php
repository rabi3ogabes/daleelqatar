<?php

declare(strict_types=1);

namespace App\Support\Discovery;

final class FetchResult
{
    public function __construct(
        public readonly string $url,
        public readonly int $status,
        public readonly string $body,
        public readonly string $contentType,
    ) {}

    public function isHtml(): bool
    {
        return $this->contentType === '' || str_contains($this->contentType, 'html')
            || (! str_contains($this->contentType, 'xml') && preg_match('/^\s*<(!doctype html|html)/i', $this->body) === 1);
    }
}
