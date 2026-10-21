<?php

declare(strict_types=1);

namespace App\Support\Discovery;

use Illuminate\Support\Str;

/**
 * Minimal robots.txt (RFC 9309) reader: picks the group for our user agent
 * (or "*"), applies longest-match Allow/Disallow with "*" and "$" wildcards,
 * and exposes Crawl-delay and Sitemap lines.
 */
final class RobotsTxt
{
    /** @param list<array{allow: bool, path: string}> $rules */
    private function __construct(
        private readonly array $rules,
        public readonly ?float $crawlDelay,
        /** @var list<string> */
        public readonly array $sitemaps,
        private readonly bool $disallowAll = false,
    ) {}

    public static function allowAll(): self
    {
        return new self([], null, []);
    }

    /** Used when robots.txt cannot be read because the server is failing. */
    public static function disallowAll(): self
    {
        return new self([], null, [], true);
    }

    public static function parse(string $content, string $agent): self
    {
        $agent = Str::lower($agent);
        $groups = [];
        $sitemaps = [];
        $current = null;
        $lastWasAgent = false;

        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            $line = trim((string) preg_replace('/#.*$/', '', $line));
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }
            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = Str::lower($field);

            if ($field === 'sitemap') {
                $sitemaps[] = $value;

                continue;
            }
            if ($field === 'user-agent') {
                if (! $lastWasAgent || $current === null) {
                    $groups[] = ['agents' => [], 'rules' => [], 'delay' => null];
                    $current = array_key_last($groups);
                }
                $groups[$current]['agents'][] = Str::lower($value);
                $lastWasAgent = true;

                continue;
            }
            $lastWasAgent = false;
            if ($current === null) {
                continue;
            }
            if ($field === 'allow' || $field === 'disallow') {
                if ($value !== '') {
                    $groups[$current]['rules'][] = ['allow' => $field === 'allow', 'path' => $value];
                }
            } elseif ($field === 'crawl-delay' && is_numeric($value)) {
                $groups[$current]['delay'] = (float) $value;
            }
        }

        // The most specific group naming our product token wins; "*" otherwise.
        $chosen = null;
        $chosenLength = -1;
        foreach ($groups as $group) {
            foreach ($group['agents'] as $name) {
                $len = $name === '*' ? 0 : (str_contains($agent, $name) ? strlen($name) : -1);
                if ($len > $chosenLength) {
                    [$chosen, $chosenLength] = [$group, $len];
                }
            }
        }

        return new self($chosen['rules'] ?? [], $chosen['delay'] ?? null, array_values(array_unique($sitemaps)));
    }

    public function allows(string $url): bool
    {
        if ($this->disallowAll) {
            return false;
        }
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
        if (($query = parse_url($url, PHP_URL_QUERY)) !== null && $query !== false) {
            $path .= '?'.$query;
        }

        $best = null;
        $bestLength = -1;
        foreach ($this->rules as $rule) {
            if (! $this->matches($rule['path'], $path)) {
                continue;
            }
            $len = strlen($rule['path']);
            // Longest match wins; on a tie Allow wins (RFC 9309 §2.2.2).
            if ($len > $bestLength || ($len === $bestLength && $rule['allow'])) {
                [$best, $bestLength] = [$rule, $len];
            }
        }

        return $best === null || $best['allow'];
    }

    private function matches(string $pattern, string $path): bool
    {
        $anchored = str_ends_with($pattern, '$');
        $pattern = $anchored ? substr($pattern, 0, -1) : $pattern;
        $regex = '#^'.str_replace('\*', '.*', preg_quote($pattern, '#')).($anchored ? '$' : '').'#';

        return (bool) preg_match($regex, rawurldecode($path)) || (bool) preg_match($regex, $path);
    }
}
