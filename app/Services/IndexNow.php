<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Business;
use App\Support\Integrations;
use App\Support\Localization;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Pushes new and updated listing URLs to search engines that support IndexNow
 * (Bing, Yandex, Seznam, Naver…). Google discovers changes through the sitemap.
 * URLs are collected during the request and sent once, after the response.
 */
final class IndexNow
{
    private const ENDPOINT = 'https://api.indexnow.org/indexnow';

    /** @var array<string,true> */
    private static array $pending = [];

    private static bool $paused = false;

    public static function enabled(): bool
    {
        return Integrations::enabled() && app()->isProduction()
            && (bool) setting('integrations.indexnow', true) && (bool) setting('seo.indexing_enabled', true);
    }

    public static function queue(Business $business): void
    {
        if (self::$paused || ! $business->isPublished() || $business->is_demo || ! self::enabled()) {
            return;
        }

        if (self::$pending === []) {
            app()->terminating(fn () => self::flush());
        }
        foreach (Localization::LOCALES as $locale) {
            self::$pending[route($locale.'.business.show', ['business' => $business->slug])] = true;
        }
    }

    /** Runs $callback without per-listing pings (bulk imports submit once at the end). */
    public static function paused(callable $callback): mixed
    {
        $previous = self::$paused;
        self::$paused = true;
        try {
            return $callback();
        } finally {
            self::$paused = $previous;
        }
    }

    public static function flush(): void
    {
        $urls = array_keys(self::$pending);
        self::$pending = [];
        self::submit($urls);
    }

    /** @param list<string> $urls */
    public static function submit(array $urls): bool
    {
        if ($urls === [] || ! self::enabled()) {
            return false;
        }

        $ok = true;
        foreach (array_chunk(array_values(array_unique($urls)), 10000) as $chunk) {
            try {
                $ok = Http::timeout(5)->acceptJson()->post(self::ENDPOINT, [
                    'host' => parse_url((string) config('app.url'), PHP_URL_HOST),
                    'key' => Integrations::indexNowKey(),
                    'keyLocation' => route('indexnow.key'),
                    'urlList' => $chunk,
                ])->successful() && $ok;
            } catch (Throwable $e) {
                report($e);
                $ok = false;
            }
        }

        return $ok;
    }
}
