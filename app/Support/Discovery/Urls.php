<?php

declare(strict_types=1);

namespace App\Support\Discovery;

use Illuminate\Support\Str;

/** URL helpers: resolving relative links and normalising URLs so each page is queued once. */
final class Urls
{
    /** Resolves an href found on $base into an absolute URL (null for javascript:, mailto:, etc.). */
    public static function resolve(string $base, string $href): ?string
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5));
        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }
        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $href)) {
            return preg_match('#^https?://#i', $href) ? self::normalize($href) : null;
        }

        $b = parse_url($base);
        if (! isset($b['scheme'], $b['host'])) {
            return null;
        }
        $origin = $b['scheme'].'://'.$b['host'].(isset($b['port']) ? ':'.$b['port'] : '');

        if (str_starts_with($href, '//')) {
            return self::normalize($b['scheme'].':'.$href);
        }
        if (str_starts_with($href, '/')) {
            return self::normalize($origin.$href);
        }
        if (str_starts_with($href, '?')) {
            return self::normalize($origin.($b['path'] ?? '/').$href);
        }

        $dir = preg_replace('#/[^/]*$#', '/', $b['path'] ?? '/') ?: '/';

        return self::normalize($origin.$dir.$href);
    }

    public static function normalize(string $url): ?string
    {
        $p = parse_url(trim($url));
        if (! isset($p['scheme'], $p['host']) || ! in_array(Str::lower($p['scheme']), ['http', 'https'], true)) {
            return null;
        }

        // Collapse "." and ".." segments.
        $segments = [];
        foreach (explode('/', $p['path'] ?? '/') as $segment) {
            if ($segment === '..') {
                array_pop($segments);
            } elseif ($segment !== '.') {
                $segments[] = $segment;
            }
        }
        $path = implode('/', $segments);
        $path = $path === '' || $path[0] !== '/' ? '/'.ltrim($path, '/') : $path;

        $query = '';
        if (! empty($p['query'])) {
            parse_str($p['query'], $params);
            $params = array_filter($params, fn ($k) => ! preg_match('/^(utm_|fbclid$|gclid$|mc_|ref$|sessionid$|phpsessid$)/i', (string) $k), ARRAY_FILTER_USE_KEY);
            ksort($params);
            $query = $params ? '?'.http_build_query($params) : '';
        }

        $url = Str::lower($p['scheme']).'://'.Str::lower($p['host']).(isset($p['port']) ? ':'.$p['port'] : '').$path.$query;

        return strlen($url) <= 2000 ? $url : null;
    }

    public static function hash(string $url): string
    {
        return sha1($url);
    }

    /** Wraps an administrator's URL pattern into a safe, case-insensitive regex (null when invalid). */
    public static function pattern(?string $pattern): ?string
    {
        $pattern = trim((string) $pattern);
        if ($pattern === '') {
            return null;
        }
        $regex = '#'.str_replace('#', '\#', $pattern).'#iu';

        return @preg_match($regex, '') === false ? null : $regex;
    }
}
