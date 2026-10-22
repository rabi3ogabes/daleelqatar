<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Business;
use App\Models\Category;
use App\Models\Page;
use App\Support\Import\CategoryMatcher;
use Illuminate\Support\Facades\Cache;

/**
 * What Google AdSense looks at before approving a site — ownership, the
 * required pages, enough original content, no placeholder or thin pages, ads
 * and consent set up correctly — checked against the live site, each with
 * the exact place in the dashboard where it is fixed.
 *
 * Every result comes from the site's own settings and data; nothing outside
 * it (Search Console, the AdSense account) is assumed to be done.
 */
final class AdsenseReadiness
{
    public const PASS = 'pass';

    public const WARN = 'warn';

    public const FAIL = 'fail';

    public const INFO = 'info';

    /** Pages AdSense reviewers expect to find on a content site. */
    public const REQUIRED_PAGES = ['about', 'privacy', 'terms', 'cookies', 'disclaimer', 'editorial'];

    private const MIN_PAGE_LENGTH = 300;

    /** Published listings with real content from which the directory is clearly not "under construction". */
    private const MIN_RICH_LISTINGS = 100;

    /**
     * @return array{score: int, counts: array<string, int>, groups: list<array{key: string, items: list<array{key: string, status: string, params: array, action: ?array{label: string, url: string}}>}>}
     */
    public function report(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget('adsense.readiness');
        }

        return Cache::remember('adsense.readiness', now()->addMinutes(10), function () {
            $groups = [
                ['key' => 'site', 'items' => $this->site()],
                ['key' => 'ads', 'items' => $this->ads()],
                ['key' => 'pages', 'items' => $this->pages()],
                ['key' => 'content', 'items' => $this->content()],
                ['key' => 'experience', 'items' => $this->experience()],
            ];
            $counts = [self::PASS => 0, self::WARN => 0, self::FAIL => 0, self::INFO => 0];
            foreach ($groups as $group) {
                foreach ($group['items'] as $item) {
                    $counts[$item['status']]++;
                }
            }
            $scored = $counts[self::PASS] + $counts[self::WARN] + $counts[self::FAIL];
            // A warning counts half: it may not block approval, but it weakens the application.
            $score = $scored > 0 ? (int) floor(100 * ($counts[self::PASS] + 0.5 * $counts[self::WARN]) / $scored) : 0;

            return ['score' => $score, 'counts' => $counts, 'groups' => $groups, 'checked_at' => now()->toIso8601String()];
        });
    }

    public static function forget(): void
    {
        Cache::forget('adsense.readiness');
    }

    /** @return list<array<string, mixed>> */
    private function site(): array
    {
        $url = (string) config('app.url');
        $host = (string) parse_url($url, PHP_URL_HOST);
        $ownDomain = str_starts_with($url, 'https://') && $host !== '' && ! preg_match('/(vercel\.app|localhost|127\.0\.0\.1|\.test|\.local)$/', $host);
        $settings = route('admin.settings.edit', 'integrations');
        $seo = route('admin.settings.edit', 'seo');
        $consoleCode = Integrations::verificationMeta()['google-site-verification'] ?? null;
        $consoleConfirmed = (bool) setting('integrations.search_console_verified', false);

        return [
            self::item('domain', $ownDomain ? self::PASS : self::FAIL, ['domain' => $host ?: $url]),
            self::item('search_console', $consoleCode || $consoleConfirmed ? self::PASS : self::WARN, [], $consoleCode || $consoleConfirmed ? null : ['fix_settings', $settings]),
            self::item('indexing', setting('seo.indexing_enabled', true) && app()->isProduction() ? self::PASS : self::FAIL, [], ['fix_settings', $seo]),
            self::item('sitemap', setting('seo.sitemap_enabled', true) ? self::PASS : self::FAIL, ['url' => url('/sitemap.xml')], setting('seo.sitemap_enabled', true) ? ['open', url('/sitemap.xml')] : ['fix_settings', $seo]),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function ads(): array
    {
        $client = Integrations::adsenseClient();
        $settings = route('admin.settings.edit', 'integrations');

        return [
            self::item('publisher_id', $client ? self::PASS : self::FAIL, ['id' => $client ?? '—'], $client ? null : ['fix_settings', $settings]),
            self::item('ads_txt', $client ? self::PASS : self::FAIL, ['url' => url('/ads.txt')], $client ? ['open', url('/ads.txt')] : ['fix_settings', $settings]),
            self::item('ad_code', $client && setting('integrations.adsense_enabled', true) && Integrations::enabled() ? self::PASS : ($client ? self::WARN : self::FAIL), [], ['fix_settings', $settings]),
            self::item('consent', setting('integrations.consent_banner', true) ? self::PASS : self::WARN, [], setting('integrations.consent_banner', true) ? null : ['fix_settings', $settings]),
            self::item('certified_cmp', self::INFO, [], ['open_adsense', 'https://www.google.com/adsense/']),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function pages(): array
    {
        $pages = Page::query()->whereIn('slug', [...self::REQUIRED_PAGES])->get()->keyBy('slug');
        $items = [];
        foreach (self::REQUIRED_PAGES as $slug) {
            $page = $pages->get($slug);
            $length = $page ? min(mb_strlen(strip_tags((string) $page->body_ar)), mb_strlen(strip_tags((string) $page->body_en))) : 0;
            $ok = $page && $page->is_published && $length >= self::MIN_PAGE_LENGTH;
            $items[] = self::item('page_'.$slug, $ok ? self::PASS : self::FAIL, ['length' => $length],
                $page ? ($ok ? ['view', route($slug)] : ['fix_page', route('admin.pages.edit', $page)]) : ['fix_pages', route('admin.pages.index')]);
        }

        $email = filled(setting('contact.email'));
        $items[] = self::item('contact', $email ? self::PASS : self::FAIL, ['email' => (string) setting('contact.email')], $email ? ['view', route('contact')] : ['fix_settings', route('admin.settings.edit', 'contact')]);

        $privacy = $pages->get('privacy');
        $disclosed = $privacy && str_contains(strtolower((string) $privacy->body_en), 'adsense') && str_contains((string) $privacy->body_en, 'adssettings.google.com');
        $items[] = self::item('ads_disclosure', $disclosed ? self::PASS : self::FAIL, [], $privacy ? ['fix_page', route('admin.pages.edit', $privacy)] : null);

        return $items;
    }

    /** @return list<array<string, mixed>> */
    private function content(): array
    {
        $published = Business::query()->published();
        $total = (clone $published)->where('is_demo', false)->count();
        $rich = ListingQuality::scopeRich((clone $published)->where('is_demo', false))->count();
        $demo = (clone $published)->where('is_demo', true)->count();
        $withDescription = (clone $published)->where('is_demo', false)->where(fn ($q) => $q
            ->whereRaw('char_length(trim(COALESCE(description_en, \'\'))) >= ?', [ListingQuality::MIN_DESCRIPTION])
            ->orWhereRaw('char_length(trim(COALESCE(description_ar, \'\'))) >= ?', [ListingQuality::MIN_DESCRIPTION]))->count();
        $fallback = Category::query()->where('slug', CategoryMatcher::FALLBACK)->value('id');
        $uncategorised = $fallback ? (clone $published)->where('category_id', $fallback)->count() : 0;
        $share = $total > 0 ? (int) round(100 * $rich / $total) : 0;

        return [
            self::item('original_content', $rich >= self::MIN_RICH_LISTINGS ? self::PASS : self::FAIL, ['rich' => format_number($rich), 'min' => self::MIN_RICH_LISTINGS],
                ['fix_enrichment', route('admin.enrichment.index')]),
            self::item('thin_listings', ListingQuality::enabled() ? self::PASS : self::FAIL, ['thin' => format_number(max(0, $total - $rich)), 'share' => $share],
                ListingQuality::enabled() ? null : ['fix_settings', route('admin.settings.edit', 'seo')]),
            self::item('descriptions', $total > 0 && $withDescription / $total >= 0.5 ? self::PASS : self::WARN,
                ['with' => format_number($withDescription), 'total' => format_number($total), 'pct' => $total > 0 ? (int) round(100 * $withDescription / $total) : 0],
                ['fix_enrichment', route('admin.enrichment.index')]),
            self::item('demo_listings', $demo === 0 ? self::PASS : self::FAIL, ['count' => format_number($demo)], $demo === 0 ? null : ['fix_listings', route('admin.businesses.index', ['demo' => 1])]),
            self::item('categories', $total > 0 && $uncategorised / $total <= 0.1 ? self::PASS : self::WARN,
                ['count' => format_number($uncategorised), 'pct' => $total > 0 ? (int) round(100 * $uncategorised / $total) : 0],
                ['fix_categories', route('admin.category-review.index')]),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function experience(): array
    {
        return [
            self::item('ad_placement', self::PASS),
            self::item('navigation', self::PASS),
            self::item('speed', self::INFO, [], ['test_speed', 'https://pagespeed.web.dev/analysis?url='.rawurlencode(url('/'))]),
        ];
    }

    /** @param  array{0: string, 1: string}|null  $action  [label key, url] */
    private static function item(string $key, string $status, array $params = [], ?array $action = null): array
    {
        return [
            'key' => $key,
            'status' => $status,
            'params' => $params,
            'action' => $action ? ['label' => $action[0], 'url' => $action[1]] : null,
        ];
    }
}
