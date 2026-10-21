<?php

declare(strict_types=1);

namespace App\Support\Search;

use App\Contracts\BusinessSearchEngine;
use App\Models\Business;
use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class PostgresBusinessSearch implements BusinessSearchEngine
{
    public function search(SearchCriteria $c): LengthAwarePaginator
    {
        $query = Business::query()
            ->published()
            ->select('businesses.*')
            ->with(['category:id,name_ar,name_en,slug,icon', 'municipality:id,name_ar,name_en,slug', 'area:id,name_ar,name_en,slug,municipality_id', 'images' => fn ($q) => $q->limit(1)]);

        $tsQuery = null;
        $normalized = null;

        if ($c->query !== null) {
            $normalized = ArabicNormalizer::normalize($c->query);
            $tokens = array_values(array_filter(preg_split('/\s+/u', trim(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized) ?? '')) ?: []));
            // Tokens contain only letters/digits, so the tsquery syntax cannot be injected; values are bound anyway.
            $tsQuery = $tokens ? implode(' & ', array_map(fn ($t) => $t.':*', array_slice($tokens, 0, 8))) : null;
            $like = '%'.addcslashes($normalized, '%_\\').'%';

            $categoryIds = Category::query()->active()
                ->where(fn (Builder $w) => $w->whereRaw(ArabicNormalizer::sql('name_ar').' ILIKE ?', [$like])
                    ->orWhereRaw(ArabicNormalizer::sql('name_en').' ILIKE ?', [$like]))
                ->pluck('id')->all();

            // Searching a place name ("Lusail", "الوكرة") returns businesses located there.
            $areaIds = \App\Models\Area::query()->active()
                ->where(fn (Builder $w) => $w->whereRaw(ArabicNormalizer::sql('name_ar').' ILIKE ?', [$like])
                    ->orWhereRaw(ArabicNormalizer::sql('name_en').' ILIKE ?', [$like]))
                ->pluck('id')->all();
            $municipalityIds = \App\Models\Municipality::query()->active()
                ->where(fn (Builder $w) => $w->whereRaw(ArabicNormalizer::sql('name_ar').' ILIKE ?', [$like])
                    ->orWhereRaw(ArabicNormalizer::sql('name_en').' ILIKE ?', [$like]))
                ->pluck('id')->all();

            $query->where(function (Builder $w) use ($tsQuery, $like, $normalized, $categoryIds, $areaIds, $municipalityIds) {
                if ($tsQuery) {
                    $w->orWhereRaw("businesses.search_vector @@ to_tsquery('simple', ?)", [$tsQuery]);
                }
                $w->orWhereRaw('businesses.search_document ILIKE ?', [$like]);
                if (mb_strlen($normalized) >= 4) {
                    // Typo tolerance
                    $w->orWhereRaw('word_similarity(?, businesses.search_document) >= 0.5', [$normalized]);
                }
                if ($categoryIds) {
                    $w->orWhereIn('businesses.category_id', $categoryIds);
                }
                if ($areaIds) {
                    $w->orWhereIn('businesses.area_id', $areaIds);
                }
                if ($municipalityIds) {
                    $w->orWhereIn('businesses.municipality_id', $municipalityIds);
                }
            });

            $query->selectRaw(
                '('.($tsQuery ? "ts_rank(businesses.search_vector, to_tsquery('simple', ?)) * 2 + " : '').
                "word_similarity(?, businesses.search_document) + CASE WHEN businesses.is_verified THEN 0.05 ELSE 0 END) AS relevance",
                $tsQuery ? [$tsQuery, $normalized] : [$normalized]
            );
        }

        if ($c->category) {
            $query->inCategory($c->category);
        }
        if ($c->municipality) {
            $query->where('businesses.municipality_id', $c->municipality->id);
        }
        if ($c->area) {
            $query->where('businesses.area_id', $c->area->id);
        }
        if ($c->verifiedOnly) {
            $query->verified();
        }
        if ($c->featuredOnly) {
            $query->featured();
        }
        if ($c->minRating) {
            $query->where('businesses.rating_avg', '>=', $c->minRating);
        }
        if ($c->openNow) {
            $query->openNow();
        }

        match ($c->sort) {
            'newest' => $query->orderByDesc('businesses.published_at'),
            'rating' => $query->orderByRaw('businesses.rating_avg DESC NULLS LAST')->orderByDesc('businesses.reviews_count'),
            'popular' => $query->orderByRaw('(businesses.views_count + businesses.favorites_count * 5) DESC'),
            'name' => $query->orderBy('businesses.'.Business::localizedColumn('name')),
            default => $c->query !== null
                ? $query->orderByDesc('relevance')
                : $query->orderByRaw('(businesses.is_featured AND (businesses.featured_until IS NULL OR businesses.featured_until > now())) DESC')
                    ->orderByDesc('businesses.is_verified')->orderByDesc('businesses.published_at'),
        };
        $query->orderBy('businesses.id');

        return $query->paginate($c->perPage, ['*'], 'page', $c->page)->withQueryString();
    }
}
