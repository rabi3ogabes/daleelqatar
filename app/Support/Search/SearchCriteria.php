<?php

declare(strict_types=1);

namespace App\Support\Search;

use App\Models\Area;
use App\Models\Category;
use App\Models\Municipality;
use Illuminate\Http\Request;

/**
 * Immutable description of a directory search. Built from web query strings
 * or API parameters; resolved filter models are null when the slug is unknown.
 */
final class SearchCriteria
{
    public const SORTS = ['relevance', 'newest', 'rating', 'popular', 'name'];

    public function __construct(
        public readonly ?string $query = null,
        public readonly ?Category $category = null,
        public readonly ?Municipality $municipality = null,
        public readonly ?Area $area = null,
        public readonly bool $verifiedOnly = false,
        public readonly ?int $minRating = null,
        public readonly bool $openNow = false,
        public readonly bool $featuredOnly = false,
        public readonly string $sort = 'relevance',
        public readonly int $perPage = 12,
        public readonly int $page = 1,
    ) {}

    public static function fromRequest(Request $request, int $defaultPerPage = 12, int $maxPerPage = 48): self
    {
        $q = trim((string) $request->query('q', ''));
        $q = mb_substr(preg_replace('/\s+/u', ' ', $q) ?? '', 0, 120);

        $category = ($slug = $request->query('category')) && is_string($slug)
            ? Category::query()->active()->where('slug', $slug)->first() : null;
        $municipality = ($slug = $request->query('municipality')) && is_string($slug)
            ? Municipality::query()->active()->where('slug', $slug)->first() : null;
        $area = ($slug = $request->query('area')) && is_string($slug)
            ? Area::query()->active()->where('slug', $slug)->first() : null;
        if ($area && ! $municipality) {
            $municipality = $area->municipality;
        }
        if ($area && $municipality && $area->municipality_id !== $municipality->id) {
            $area = null;
        }

        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'relevance';
        $rating = (int) $request->query('rating', 0);

        return new self(
            query: $q !== '' ? $q : null,
            category: $category,
            municipality: $municipality,
            area: $area,
            verifiedOnly: $request->boolean('verified'),
            minRating: $rating >= 1 && $rating <= 5 ? $rating : null,
            openNow: $request->boolean('open_now'),
            featuredOnly: $request->boolean('featured'),
            sort: $sort,
            perPage: max(1, min($maxPerPage, (int) $request->query('per_page', $defaultPerPage))),
            page: max(1, (int) $request->query('page', 1)),
        );
    }

    public function hasFilters(): bool
    {
        return $this->query !== null || $this->category || $this->municipality || $this->area
            || $this->verifiedOnly || $this->minRating || $this->openNow;
    }

    /** Query-string representation used for shareable URLs and pagination links. */
    public function toQuery(): array
    {
        return array_filter([
            'q' => $this->query,
            'category' => $this->category?->slug,
            'municipality' => $this->municipality?->slug,
            'area' => $this->area?->slug,
            'verified' => $this->verifiedOnly ? 1 : null,
            'rating' => $this->minRating,
            'open_now' => $this->openNow ? 1 : null,
            'sort' => $this->sort !== 'relevance' ? $this->sort : null,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
