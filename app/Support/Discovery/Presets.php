<?php

declare(strict_types=1);

namespace App\Support\Discovery;

/**
 * Ready-made settings for directories already studied, so adding one is a
 * single click. Each is an ordinary source afterwards and can be edited.
 */
final class Presets
{
    public const ALL = [
        'qbcd' => [
            'name' => 'Qatar Business Contact Directory (QBCD)',
            'base_url' => 'https://www.qatarcontact.com',
            // No sitemap: the companies are listed alphabetically on ~400 numbered pages of 10.
            'discovery' => 'pages',
            'start_urls' => 'https://www.qatarcontact.com/listings?page={page}',
            'url_pattern' => '/listing/',
            'settings' => [
                'delay_ms' => 2500,
                'max_pages_per_run' => 10,
                'rediscover_hours' => 168,
                'publish' => false,
                'search_url' => 'https://www.qatarcontact.com/listings?name={query}',
            ],
        ],
    ];

    /** @return array<string, mixed>|null */
    public static function get(?string $key): ?array
    {
        return $key !== null ? (self::ALL[$key] ?? null) : null;
    }
}
