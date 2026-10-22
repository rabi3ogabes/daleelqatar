<?php

declare(strict_types=1);

namespace App\Support\Discovery;

use App\Models\ScrapeSource;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Finds company detail pages on a source site and queues them in scrape_urls.
 * The work list ("frontier" of sitemaps and listing pages still to read) is
 * stored on the source, so discovery of a large site continues across runs
 * that each fit inside a serverless time limit.
 */
final class Discoverer
{
    private const MAX_FRONTIER = 1000;

    private const MAX_VISITED = 3000;

    public function __construct(private readonly Fetcher $fetcher) {}

    /**
     * @param  Closure(int): bool  $hasTime  receives the wait (ms) before the next request
     * @return array{queued: int, complete: bool, errors: int}
     */
    public function run(ScrapeSource $source, Closure $hasTime): array
    {
        $stats = (array) $source->stats;
        $frontier = (array) ($stats['frontier'] ?? []);
        $visited = array_flip((array) ($stats['visited'] ?? []));
        $pattern = Urls::pattern($source->url_pattern);
        $queued = 0;
        $errors = 0;

        if ($frontier === []) {
            $frontier = $this->seed($source);
            $visited = [];
            if ($source->discovery === 'urls') {
                $queued += $this->enqueue($source, array_column($frontier, 0));
                $frontier = [];
            }
        }

        $maxDepth = (int) $source->setting('max_depth', 2);
        $maxListPages = (int) $source->setting('max_list_pages', 200);

        while ($frontier !== [] && $hasTime($this->fetcher->waitFor(self::target($frontier[0])))) {
            $item = array_shift($frontier);
            [, $depth, $kind] = $item;
            $url = self::target($item);
            $visited[substr(sha1($url), 0, 12)] = true;

            try {
                $page = $this->fetcher->get($url, $source->base_url);
            } catch (DiscoveryException $e) {
                $errors++;
                // A numbered listing page that failed is tried again on the next run, once.
                if ($kind === 'pages' && $e->retryable && empty($item[4])) {
                    $frontier[] = [$item[0], $depth, 'pages', $item[3] ?? null, 1];
                }

                continue;
            }

            if ($kind === 'pages') {
                $frontier = $this->listingPage($source, $page, $item, $frontier, $pattern, $queued);

                continue;
            }

            if ($kind === 'sitemap') {
                [$children, $pages] = self::parseSitemap($page->body);
                foreach ($children as $child) {
                    $frontier = $this->push($frontier, $visited, $source, $child, $depth + 1, 'sitemap');
                }
                $pages = array_filter($pages, fn ($u) => UrlGuard::sameSite($u, $source->base_url) && ($pattern === null || preg_match($pattern, $u)));
                $queued += $this->enqueue($source, $pages);

                continue;
            }

            // A listing page: queue detail links, follow other links on the same site.
            $details = [];
            foreach (self::links($page->body, $page->url) as $link) {
                if (! UrlGuard::sameSite($link, $source->base_url)) {
                    continue;
                }
                if ($pattern !== null && preg_match($pattern, $link)) {
                    $details[] = $link;
                } elseif ($depth < $maxDepth && count($visited) < $maxListPages) {
                    $frontier = $this->push($frontier, $visited, $source, $link, $depth + 1, 'page');
                }
            }
            $queued += $this->enqueue($source, $details);
        }

        $complete = $frontier === [];
        $stats['frontier'] = array_slice($frontier, 0, self::MAX_FRONTIER);
        $stats['visited'] = $complete ? [] : array_slice(array_keys($visited), -self::MAX_VISITED);
        $source->forceFill(['stats' => $stats] + ($complete ? ['last_discovered_at' => now()] : []))->save();

        return ['queued' => $queued, 'complete' => $complete, 'errors' => $errors];
    }

    /**
     * The URL a frontier item stands for. Numbered listings ("pages" mode) keep their
     * template ("…/listings?page={page}") and the page number in the depth slot.
     */
    private static function target(array $item): string
    {
        return ($item[2] ?? null) === 'pages' ? self::pageUrl($item[0], (int) $item[1]) : $item[0];
    }

    public static function pageUrl(string $template, int $page): string
    {
        return str_replace('{page}', (string) max(1, $page), $template);
    }

    /**
     * One numbered listing page: queue its company links, then move on to the next number
     * until the list ends — an empty page, a page repeating the previous one, the last page
     * number the site links to, or the configured maximum.
     */
    private function listingPage(ScrapeSource $source, FetchResult $page, array $item, array $frontier, ?string $pattern, int &$queued): array
    {
        [$template, $number] = [$item[0], (int) $item[1]];

        $details = [];
        $last = 0;
        $numbered = self::numberPattern($template);
        foreach (self::links($page->body, $page->url) as $link) {
            if (! UrlGuard::sameSite($link, $source->base_url)) {
                continue;
            }
            if ($pattern !== null ? preg_match($pattern, $link) : false) {
                $details[] = $link;
            } elseif ($numbered !== null && preg_match($numbered, $link, $m)) {
                $last = max($last, (int) $m[1]);
            }
        }
        $details = array_values(array_unique($details));
        $queued += $this->enqueue($source, $details);

        sort($details);
        $signature = $details === [] ? null : substr(sha1(implode('|', $details)), 0, 16);
        $limit = (int) $source->setting('max_list_pages', 1000);
        $ended = $signature === null               // nothing listed: past the end
            || $signature === ($item[3] ?? null)    // the site repeats its last page for any number
            || ($last > 0 && $number >= $last)      // the pager links to no higher page
            || $number >= $limit;

        if (! $ended) {
            $frontier[] = [$template, $number + 1, 'pages', $signature];
        }

        return $frontier;
    }

    /** "https://site/listings?page={page}" → a regex that captures the page number of links built the same way. */
    private static function numberPattern(string $template): ?string
    {
        $normalized = Urls::normalize(str_replace('{page}', '987654321', $template));
        if ($normalized === null) {
            return null;
        }
        $parts = explode('987654321', $normalized, 2);

        return count($parts) === 2 ? '#^'.preg_quote($parts[0], '#').'(\d+)'.preg_quote($parts[1], '#').'$#i' : null;
    }

    /** @return list<array{0: string, 1: int, 2: string}> */
    private function seed(ScrapeSource $source): array
    {
        $starts = array_values(array_filter(array_map(fn ($u) => Urls::normalize($u), $source->startUrls())));

        if ($source->discovery === 'sitemap' && $starts === []) {
            $starts = array_values(array_filter(
                array_map(fn ($u) => Urls::normalize($u), $this->fetcher->robotsFor($source->base_url)->sitemaps),
                fn ($u) => $u !== null && UrlGuard::sameSite($u, $source->base_url),
            ));
            $starts = $starts ?: [rtrim(Fetcher::origin($source->base_url), '/').'/sitemap.xml'];
        }
        if ($source->discovery === 'pages') {
            $templates = array_values(array_filter($source->startUrls(), fn ($u) => str_contains($u, '{page}') && Urls::normalize(self::pageUrl($u, 1)) !== null));

            return array_map(fn ($t) => [$t, max(1, (int) $source->setting('first_page', 1)), 'pages', null], $templates);
        }
        if ($starts === []) {
            $starts = [Urls::normalize($source->base_url) ?? $source->base_url];
        }

        $kind = $source->discovery === 'sitemap' ? 'sitemap' : 'page';

        return array_map(fn ($u) => [$u, 0, $kind], $starts);
    }

    private function push(array $frontier, array $visited, ScrapeSource $source, string $url, int $depth, string $kind): array
    {
        if (isset($visited[substr(sha1($url), 0, 12)]) || count($frontier) >= self::MAX_FRONTIER || $depth > 6) {
            return $frontier;
        }
        foreach ($frontier as $item) {
            if ($item[0] === $url) {
                return $frontier;
            }
        }
        if (preg_match('/\.(jpe?g|png|gif|webp|svg|pdf|zip|docx?|xlsx?|mp4|mp3|css|js)(\?|$)/i', $url)) {
            return $frontier;
        }
        $frontier[] = [$url, $depth, $kind];

        return $frontier;
    }

    /**
     * @param  iterable<string>  $urls
     * @return int newly queued URLs
     */
    public function enqueue(ScrapeSource $source, iterable $urls): int
    {
        $limit = (int) $source->setting('max_urls', 50_000);
        $rows = [];
        $now = now();
        foreach ($urls as $url) {
            $url = Urls::normalize((string) $url);
            if ($url !== null) {
                $rows[Urls::hash($url)] = ['scrape_source_id' => $source->id, 'url' => $url, 'url_hash' => Urls::hash($url), 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now];
            }
        }
        if ($rows === []) {
            return 0;
        }

        $room = $limit - DB::table('scrape_urls')->where('scrape_source_id', $source->id)->count();
        $added = 0;
        foreach (array_chunk(array_slice(array_values($rows), 0, max(0, $room)), 500) as $chunk) {
            $added += DB::table('scrape_urls')->insertOrIgnore($chunk);
        }

        return $added;
    }

    /** @return array{0: list<string>, 1: list<string>} [child sitemaps, page URLs] */
    public static function parseSitemap(string $xml): array
    {
        preg_match_all('#<loc>\s*(?:<!\[CDATA\[)?\s*(.*?)\s*(?:\]\]>)?\s*</loc>#is', $xml, $m);
        $locs = array_values(array_filter(array_map(fn ($u) => Urls::normalize(html_entity_decode($u, ENT_QUOTES | ENT_XML1)), $m[1])));

        return preg_match('/<sitemapindex[\s>]/i', $xml) ? [$locs, []] : [[], $locs];
    }

    /** @return list<string> absolute, normalised links found on a page */
    public static function links(string $html, string $base): array
    {
        preg_match_all('/<a\s[^>]*?href\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))[^>]*>/i', $html, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $match) {
            $href = $match[1] !== '' ? $match[1] : (($match[2] ?? '') !== '' ? $match[2] : ($match[3] ?? ''));
            if (str_contains(strtolower($match[0]), 'rel="nofollow"')) {
                continue;
            }
            if (($url = Urls::resolve($base, $href)) !== null) {
                $out[$url] = true;
            }
        }

        return array_keys($out);
    }
}
