<?php

declare(strict_types=1);

namespace App\Support\Guides;

use App\Models\Area;
use App\Models\Business;
use App\Models\Category;
use App\Models\Guide;
use App\Models\Municipality;
use App\Support\Directory;
use App\Support\ListingQuality;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The listings a guide shows. Only published, real (non-demo), complete
 * listings qualify. The order uses facts only — editor's picks first, then
 * verified listings, then the most complete profiles — never invented ratings.
 */
final class GuideBuilder
{
    public const MIN_LISTINGS = 3;

    public const MIN_INTRO = 120;

    /** @param  array{category_id?: ?int, municipality_id?: ?int, area_id?: ?int, open_24h?: bool, verified_only?: bool}  $criteria */
    public function query(array $criteria): Builder
    {
        $q = Business::query()->published()->where('businesses.is_demo', false)->select('businesses.*');
        ListingQuality::scopeRich($q);

        if (! empty($criteria['category_id']) && ($category = Category::query()->find($criteria['category_id']))) {
            $q->inCategory($category);
        }
        if (! empty($criteria['area_id'])) {
            $q->where('businesses.area_id', $criteria['area_id']);
        } elseif (! empty($criteria['municipality_id'])) {
            $q->where('businesses.municipality_id', $criteria['municipality_id']);
        }
        if (! empty($criteria['verified_only'])) {
            $q->where('businesses.is_verified', true);
        }
        if (! empty($criteria['open_24h'])) {
            // Open around the clock every day of the week.
            $q->whereRaw("(SELECT COUNT(DISTINCT oh.day_of_week) FROM opening_hours oh WHERE oh.business_id = businesses.id AND oh.is_closed = false AND oh.opens_at = '00:00' AND oh.closes_at = '24:00') = 7");
        }

        return $q;
    }

    /** @return Collection<int, Business> */
    public function listings(Guide $guide): Collection
    {
        $pinned = $guide->pinned()->published()->where('businesses.is_demo', false)->with(Directory::cardRelations())->get();
        $rest = $this->query($this->criteria($guide))
            ->whereNotIn('businesses.id', $pinned->pluck('id'))
            ->with(Directory::cardRelations())
            ->orderByDesc('businesses.is_verified')
            ->orderByRaw(self::completenessSql().' DESC')
            ->orderByDesc('businesses.reviews_count')
            ->orderBy('businesses.'.Business::localizedColumn('name'))
            ->limit(max(0, $guide->max_items - $pinned->count()))
            ->get();

        return $pinned->concat($rest)->take($guide->max_items)->values();
    }

    public function count(array $criteria): int
    {
        return $this->query($criteria)->count();
    }

    /** A guide is worth showing to search engines only with enough listings and its own introduction. */
    public function indexable(Guide $guide, int $listings): bool
    {
        return $guide->isPublished() && $listings >= self::MIN_LISTINGS
            && mb_strlen(trim((string) $guide->t('intro'))) >= self::MIN_INTRO;
    }

    public function criteria(Guide $guide): array
    {
        return $guide->only(['category_id', 'municipality_id', 'area_id', 'open_24h', 'verified_only']);
    }

    /**
     * Category × place combinations with enough complete listings and no guide yet — ideas for the editor.
     *
     * @return list<array{category: Category, municipality: Municipality, count: int}>
     */
    public function suggestions(int $limit = 24): array
    {
        $existing = Guide::query()->whereNull('area_id')->where('open_24h', false)->get(['category_id', 'municipality_id'])
            ->map(fn ($g) => $g->category_id.':'.$g->municipality_id)->all();

        $q = Business::query()->published()->where('businesses.is_demo', false)->whereNotNull('businesses.municipality_id');
        ListingQuality::scopeRich($q);
        $rows = $q->selectRaw('businesses.category_id, businesses.municipality_id, COUNT(*) AS n')
            ->groupBy('businesses.category_id', 'businesses.municipality_id')
            ->havingRaw('COUNT(*) >= ?', [5])->orderByDesc('n')->limit($limit * 2)->get();

        $categories = Category::query()->whereIn('id', $rows->pluck('category_id'))->get()->keyBy('id');
        $places = Municipality::query()->whereIn('id', $rows->pluck('municipality_id'))->get()->keyBy('id');

        return $rows->reject(fn ($r) => in_array($r->category_id.':'.$r->municipality_id, $existing, true))
            ->filter(fn ($r) => isset($categories[$r->category_id], $places[$r->municipality_id]))
            ->take($limit)
            ->map(fn ($r) => ['category' => $categories[$r->category_id], 'municipality' => $places[$r->municipality_id], 'count' => (int) $r->n])
            ->values()->all();
    }

    /** Factual starting text for a new guide, which the editor rewrites in their own words. */
    public function draft(?Category $category, ?Municipality $municipality, ?Area $area, bool $open24h, int $count): array
    {
        $out = [];
        foreach (['ar', 'en'] as $locale) {
            $place = $area?->t('name', $locale) ?? $municipality?->t('name', $locale);
            $what = $category?->t('name', $locale) ?? __('guides.draft.businesses', [], $locale);
            $title = $open24h
                ? __('guides.draft.title_24h', ['what' => $what, 'place' => $place ?? __('guides.draft.qatar', [], $locale)], $locale)
                : ($place ? __('guides.draft.title_in', ['what' => $what, 'place' => $place], $locale) : __('guides.draft.title', ['what' => $what], $locale));
            $out['title_'.$locale] = $title;
            $out['intro_'.$locale] = __('guides.draft.intro', ['count' => $count, 'what' => $what, 'place' => $place ?? __('guides.draft.qatar', [], $locale)], $locale);
        }

        return $out;
    }

    private static function completenessSql(): string
    {
        return "((CASE WHEN COALESCE(businesses.logo_path, '') <> '' THEN 1 ELSE 0 END)
            + (CASE WHEN COALESCE(businesses.cover_path, '') <> '' THEN 1 ELSE 0 END)
            + (CASE WHEN char_length(COALESCE(businesses.description_en, businesses.description_ar, '')) >= 120 THEN 1 ELSE 0 END)
            + (CASE WHEN EXISTS (SELECT 1 FROM opening_hours oh WHERE oh.business_id = businesses.id) THEN 1 ELSE 0 END)
            + (CASE WHEN businesses.latitude IS NOT NULL THEN 1 ELSE 0 END)
            + (CASE WHEN COALESCE(businesses.whatsapp, businesses.phone, '') <> '' THEN 1 ELSE 0 END))";
    }
}
