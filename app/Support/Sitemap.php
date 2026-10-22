<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Business;
use App\Models\Category;
use App\Models\Guide;
use App\Models\Municipality;
use App\Models\Offer;
use App\Models\Page;
use App\Support\Guides\GuideBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * XML sitemaps for Google Search Console and Bing: /sitemap.xml is an index of
 * smaller files (static pages, taxonomy, offers and listings in chunks), each
 * with Arabic/English hreflang alternates. Only published, non-demo listings
 * and non-empty category, location and A–Z pages are included.
 */
final class Sitemap
{
    public const PER_FILE = 2000;

    private const TTL_HOURS = 6;

    /** @return list<array{name:string, lastmod:?string}> */
    public static function parts(): array
    {
        return Cache::remember('sitemap.parts', now()->addHours(self::TTL_HOURS), function () {
            $businesses = self::businessQuery();
            $total = (clone $businesses)->count();
            $parts = [
                ['name' => 'pages', 'lastmod' => now()->toDateString()],
                ['name' => 'taxonomy', 'lastmod' => self::date(Category::query()->max('updated_at'))],
            ];
            if (Offer::query()->live()->exists()) {
                $parts[] = ['name' => 'offers', 'lastmod' => self::date(Offer::query()->live()->max('offers.updated_at'))];
            }
            for ($page = 1; $page <= (int) ceil($total / self::PER_FILE); $page++) {
                $lastmod = DB::query()->fromSub((clone $businesses)->select('businesses.updated_at')->orderBy('businesses.id')
                    ->offset(($page - 1) * self::PER_FILE)->limit(self::PER_FILE), 'chunk')->max('updated_at');
                $parts[] = ['name' => 'businesses-'.$page, 'lastmod' => self::date($lastmod)];
            }

            return $parts;
        });
    }

    public static function isPart(string $name): bool
    {
        return in_array($name, array_column(self::parts(), 'name'), true);
    }

    public static function index(): string
    {
        return Cache::remember('sitemap.xml', now()->addHours(self::TTL_HOURS), fn () => self::xml(view('seo.sitemap-index', ['parts' => self::parts()])->render()));
    }

    public static function part(string $name): string
    {
        return Cache::remember('sitemap.part.'.$name, now()->addHours(self::TTL_HOURS), fn () => self::xml(view('seo.sitemap', ['entries' => self::entries($name)])->render()));
    }

    /** Builds every file so crawlers are always served from cache. Returns the number of URLs. */
    public static function warm(): int
    {
        self::flush();
        self::index();
        $urls = 0;
        foreach (self::parts() as $part) {
            $urls += substr_count(self::part($part['name']), '<url>');
        }

        return $urls;
    }

    public static function flush(): void
    {
        foreach ((array) Cache::get('sitemap.parts', []) as $part) {
            Cache::forget('sitemap.part.'.$part['name']);
        }
        Cache::forget('sitemap.parts');
        Cache::forget('sitemap.xml');
    }

    /** @return list<array{ar:string, en:string, lastmod:?string, priority:string}> */
    private static function entries(string $name): array
    {
        $entries = [];
        $add = function (string $route, array $params = [], mixed $modified = null, string $priority = '0.6') use (&$entries) {
            $entries[] = [
                'ar' => route('ar.'.$route, $params),
                'en' => route('en.'.$route, $params),
                'lastmod' => self::date($modified),
                'priority' => $priority,
            ];
        };

        if ($name === 'pages') {
            $add('home', [], now(), '1.0');
            foreach (['categories.index', 'locations.index', 'map', 'directory.az', 'offers.index', 'businesses.featured', 'businesses.latest', 'about', 'faq', 'contact', 'add-business'] as $static) {
                $add($static, [], null, '0.5');
            }
            Page::query()->where('is_published', true)->whereIn('slug', ['privacy', 'terms', 'cookies', 'disclaimer', 'editorial', 'advertise'])->get()
                ->each(fn ($p) => $add($p->slug, [], $p->updated_at, '0.2'));
            // Guides: only those search engines may index (enough listings and the editor's own introduction).
            $builder = app(GuideBuilder::class);
            $guides = Guide::query()->published()->get();
            if ($guides->isNotEmpty()) {
                $add('guides.index', [], $guides->max('updated_at'), '0.6');
            }
            $guides->each(function ($g) use ($add, $builder) {
                if ($builder->indexable($g, $builder->listings($g)->count())) {
                    $add('guides.show', ['guide' => $g->slug], $g->updated_at, '0.7');
                }
            });
        } elseif ($name === 'taxonomy') {
            $rootCounts = Directory::categoryCounts();
            $subCounts = self::businessQuery(quality: false)->selectRaw('businesses.category_id, COUNT(*) AS total')
                ->groupBy('businesses.category_id')->pluck('total', 'category_id');
            Category::query()->active()->orderBy('id')->get(['id', 'slug', 'parent_id', 'updated_at'])
                ->each(function (Category $c) use ($add, $rootCounts, $subCounts) {
                    $count = $c->parent_id === null ? ($rootCounts[$c->id] ?? 0) : (int) ($subCounts[$c->id] ?? 0);
                    if ($count > 0) {
                        $add('categories.show', ['category' => $c->slug], $c->updated_at, '0.7');
                    }
                });

            $municipalityCounts = Directory::municipalityCounts();
            Municipality::query()->active()->get(['id', 'slug', 'updated_at'])->each(function (Municipality $m) use ($add, $municipalityCounts) {
                if (($municipalityCounts[$m->id] ?? 0) > 0) {
                    $add('locations.show', ['municipality' => $m->slug], $m->updated_at, '0.6');
                }
            });

            $letters = array_keys(AzIndex::counts('ar') + AzIndex::counts('en'));
            foreach ($letters as $letter) {
                $add('directory.az.letter', ['letter' => $letter], null, '0.4');
            }
        } elseif ($name === 'offers') {
            Offer::query()->live()->select(['offers.id', 'offers.slug', 'offers.updated_at', 'offers.business_id'])->get()
                ->each(fn ($o) => $add('offers.show', ['offer' => $o->slug], $o->updated_at, '0.4'));
        } elseif (preg_match('/^businesses-(\d+)$/', $name, $m)) {
            self::businessQuery()->select(['businesses.id', 'businesses.slug', 'businesses.updated_at'])->orderBy('businesses.id')
                ->offset(((int) $m[1] - 1) * self::PER_FILE)->limit(self::PER_FILE)->get()
                ->each(fn ($b) => $add('business.show', ['business' => $b->slug], $b->updated_at, '0.8'));
        }

        return $entries;
    }

    private static function businessQuery(bool $quality = true)
    {
        $query = Business::query()->published()->where('businesses.is_demo', false);

        // Thin listings are noindexed, so they are not offered to search engines either.
        return $quality && ListingQuality::enabled() ? ListingQuality::scopeRich($query) : $query;
    }

    private static function date(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->toDateString() : null;
    }

    private static function xml(string $body): string
    {
        // Prepended here: Blade cannot hold "<?xml" (it is a PHP open tag when short_open_tag is on).
        return '<?xml version="1.0" encoding="UTF-8"?>'."\n".$body;
    }
}
