<?php

declare(strict_types=1);

namespace App\Support\Discovery;

use App\Support\Deadline;
use Throwable;

/**
 * Works out how to crawl a directory from its address alone, the way a person
 * would: look for a sitemap first; without one, open the listing and find
 * the numbered pages and the links that lead to company pages. One company
 * page is then read as a sample so the administrator sees what they would get.
 */
final class SourceAnalyzer
{
    /** Path segments that are lists or site pages, never one company. */
    private const NOT_DETAIL = ['page', 'pages', 'category', 'categories', 'tag', 'tags', 'search', 'blog', 'news', 'author', 'login', 'register',
        'account', 'cart', 'about', 'contact', 'privacy', 'terms', 'faq', 'help', 'assets', 'static', 'images', 'img', 'css', 'js', 'wp-content',
        'wp-json', 'feed', 'cdn-cgi', 'locations', 'location', 'city', 'cities', 'areas', 'instruction', 'ad', 'ads', 'events', 'jobs', 'careers'];

    /** Words that name the company list on a home page. */
    private const LIST_WORDS = ['listings', 'listing', 'companies', 'company', 'businesses', 'business', 'directory', 'members', 'suppliers', 'shops', 'stores'];

    public function __construct(private readonly Fetcher $fetcher) {}

    /**
     * @return array{base_url: string, mode: ?string, start_urls: list<string>, url_pattern: ?string, last_page: ?int,
     *               detail_count: int, sample: ?array{url: string, method: string, fields: array<string, string>}, steps: list<array{key: string, ok: bool, detail?: string}>}
     */
    public function analyze(string $address): array
    {
        $base = rtrim(Fetcher::origin($address), '/');
        $result = ['base_url' => $base, 'mode' => null, 'start_urls' => [], 'url_pattern' => null, 'last_page' => null, 'detail_count' => 0, 'sample' => null, 'steps' => []];

        // 1. A sitemap lists every company page directly.
        [$sitemap, $pages] = $this->sitemap($base);
        $pattern = $pages === [] ? null : self::detailPattern($pages, 20);
        $result['steps'][] = ['key' => 'sitemap', 'ok' => $pattern !== null, 'detail' => $sitemap];

        if ($pattern !== null) {
            $details = array_values(array_filter($pages, fn ($u) => preg_match(Urls::pattern($pattern), $u)));
            $result = array_merge($result, ['mode' => 'sitemap', 'start_urls' => $sitemap ? [$sitemap] : [], 'url_pattern' => $pattern, 'detail_count' => count($details)]);

            return $this->withSample($result, $details);
        }

        // 2. The listing: the address given, or the list a home page links to.
        $listing = $this->page($address);
        $links = $listing ? Discoverer::links($listing->body, $listing->url) : [];
        $links = array_values(array_filter($links, fn ($u) => UrlGuard::sameSite($u, $base)));
        if ($listing && self::detailPattern($links, 5) === null && ($list = self::listLink($links)) !== null) {
            $listing = $this->page($list) ?? $listing;
            $links = array_values(array_filter(Discoverer::links($listing->body, $listing->url), fn ($u) => UrlGuard::sameSite($u, $base)));
        }
        $result['steps'][] = ['key' => 'listing', 'ok' => $listing !== null, 'detail' => $listing?->url];
        if ($listing === null) {
            return $result;
        }

        $pattern = self::detailPattern($links, 5);
        $details = $pattern ? array_values(array_filter($links, fn ($u) => preg_match(Urls::pattern($pattern), $u))) : [];
        $result['steps'][] = ['key' => 'details', 'ok' => $pattern !== null, 'detail' => $pattern ? count($details).' · '.$pattern : null];

        [$template, $last] = self::pagination($links, $listing->url);
        $result['steps'][] = ['key' => 'pagination', 'ok' => $template !== null, 'detail' => $template ? $template.($last ? ' (1…'.$last.')' : '') : null];

        $result['url_pattern'] = $pattern;
        $result['detail_count'] = count($details);
        if ($template !== null) {
            $result = array_merge($result, ['mode' => 'pages', 'start_urls' => [$template], 'last_page' => $last]);
        } elseif ($pattern !== null) {
            $result = array_merge($result, ['mode' => 'links', 'start_urls' => [Urls::normalize($listing->url) ?? $listing->url]]);
        }

        return $this->withSample($result, $details);
    }

    /** @return array{0: ?string, 1: list<string>} [sitemap address, page URLs] */
    private function sitemap(string $base): array
    {
        $candidates = [];
        try {
            $candidates = $this->fetcher->robotsFor($base)->sitemaps;
        } catch (Throwable) {
            // No robots.txt: try the usual addresses.
        }
        $candidates = array_values(array_unique([...$candidates, $base.'/sitemap.xml', $base.'/sitemap_index.xml']));

        foreach (array_slice($candidates, 0, 4) as $candidate) {
            if (! UrlGuard::sameSite($candidate, $base) || ! ($page = $this->page($candidate)) || ! preg_match('/<(urlset|sitemapindex)[\s>]/i', $page->body)) {
                continue;
            }
            [$children, $pages] = Discoverer::parseSitemap($page->body);
            // An index: the company sitemap is usually named after them, otherwise take the first ones.
            usort($children, fn ($a, $b) => self::listScore($b) <=> self::listScore($a));
            foreach (array_slice($children, 0, 2) as $child) {
                if (UrlGuard::sameSite($child, $base) && ($sub = $this->page($child))) {
                    $pages = [...$pages, ...Discoverer::parseSitemap($sub->body)[1]];
                }
            }
            $pages = array_values(array_filter($pages, fn ($u) => UrlGuard::sameSite($u, $base)));
            if ($pages !== []) {
                return [$candidate, $pages];
            }
        }

        return [null, []];
    }

    private function page(string $url): ?FetchResult
    {
        try {
            $page = $this->fetcher->get($url, null);

            return $page->body !== '' ? $page : null;
        } catch (DiscoveryException) {
            return null;
        }
    }

    /** @param list<string> $details */
    private function withSample(array $result, array $details): array
    {
        foreach (array_slice($details, 0, 2) as $url) {
            if (! Deadline::has(8)) {
                break;
            }
            $page = $this->page($url);
            if ($page === null || ! $page->isHtml()) {
                continue;
            }
            $extracted = Extractor::extract($page->body, $page->url);
            if ($extracted['record'] === null) {
                continue;
            }
            $record = $extracted['record'];
            $result['sample'] = ['url' => $page->url, 'method' => $extracted['method'], 'fields' => array_filter([
                'name_en' => $record['name_en'],
                'categories' => implode(' · ', $record['categories']),
                'phone' => implode(', ', array_filter([$record['phone'], ...$record['extra_phones']])),
                'email' => implode(', ', array_filter([$record['email'], ...$record['extra_emails']])),
                'website' => $record['website'],
                'address' => $record['address'] ?? $record['city'],
                'coordinates' => $record['latitude'] !== null ? $record['latitude'].', '.$record['longitude'] : null,
                'image' => $record['image_url'] ?? $record['logo_url'],
            ], fn ($v) => $v !== null && $v !== '')];
            break;
        }
        $result['steps'][] = ['key' => 'sample', 'ok' => $result['sample'] !== null, 'detail' => $result['sample']['url'] ?? null];

        return $result;
    }

    /**
     * The address shape shared by company pages: the most common first folder of
     * addresses that end in a name ("/listing/a-r-group-trading" → "/listing/").
     *
     * @param  list<string>  $urls
     */
    public static function detailPattern(array $urls, int $minimum): ?string
    {
        $groups = [];
        foreach ($urls as $url) {
            $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
            $segments = $path === '' ? [] : explode('/', $path);
            // Language folders ("/en/company/…") belong to the prefix.
            $prefix = [];
            if (count($segments) > 2 && preg_match('/^[a-z]{2}(-[a-z]{2})?$/i', $segments[0])) {
                $prefix[] = array_shift($segments);
            }
            if (count($segments) < 2 || in_array(strtolower($segments[0]), self::NOT_DETAIL, true)) {
                continue;
            }
            $slug = end($segments);
            if (strlen($slug) < 4 || ! preg_match('/[-_]|\d/', $slug) || preg_match('/\.(jpe?g|png|gif|webp|svg|pdf|css|js|xml)$/i', $slug)) {
                continue;
            }
            $key = '/'.implode('/', [...$prefix, $segments[0]]).'/';
            $groups[$key] = ($groups[$key] ?? 0) + 1;
        }
        if ($groups === []) {
            return null;
        }
        arsort($groups);
        $key = (string) array_key_first($groups);

        return $groups[$key] >= $minimum ? $key : null;
    }

    /**
     * Numbered listing pages: "…?page=2" or "…/page/2" → a template with {page}, plus the
     * highest page number the pager shows.
     *
     * @param  list<string>  $links
     * @return array{0: ?string, 1: ?int}
     */
    public static function pagination(array $links, string $listingUrl): array
    {
        $templates = [];
        foreach ($links as $link) {
            if (preg_match('/([?&](?:page|p|pg|paged|pagenum)=)(\d+)/i', $link, $m)) {
                $template = str_replace($m[0], $m[1].'{page}', $link);
            } elseif (preg_match('#(/page/)(\d+)(/|$)#i', $link, $m)) {
                $template = str_replace($m[0], $m[1].'{page}'.$m[3], $link);
            } else {
                continue;
            }
            $templates[$template][] = (int) $m[2];
        }
        if ($templates === []) {
            return [null, null];
        }
        // The pager of this listing: the template seen most often, preferring the listing's own path.
        $listingPath = (string) parse_url($listingUrl, PHP_URL_PATH);
        uksort($templates, function ($a, $b) use ($templates, $listingPath) {
            $samePath = fn ($t) => (string) parse_url(str_replace('{page}', '1', $t), PHP_URL_PATH) === $listingPath ? 1 : 0;

            return [$samePath($b), count($templates[$b])] <=> [$samePath($a), count($templates[$a])];
        });
        $template = (string) array_key_first($templates);
        $last = max($templates[$template]);

        return [$template, $last > 1 ? $last : null];
    }

    /** @param list<string> $links */
    private static function listLink(array $links): ?string
    {
        $best = null;
        $bestScore = 0;
        foreach ($links as $link) {
            $score = self::listScore($link);
            if ($score > $bestScore) {
                [$best, $bestScore] = [$link, $score];
            }
        }

        return $best;
    }

    private static function listScore(string $url): int
    {
        $path = strtolower(trim((string) parse_url($url, PHP_URL_PATH), '/'));
        if ($path === '' || substr_count($path, '/') > 1) {
            return 0;
        }
        foreach (self::LIST_WORDS as $i => $word) {
            if (preg_match('#(^|/|-)'.$word.'(\.xml)?$#', $path)) {
                return 20 - $i;
            }
        }

        return 0;
    }
}
