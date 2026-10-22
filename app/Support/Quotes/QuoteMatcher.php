<?php

declare(strict_types=1);

namespace App\Support\Quotes;

use App\Enums\UserStatus;
use App\Models\Area;
use App\Models\Business;
use App\Models\Category;
use App\Models\Municipality;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Chooses which businesses receive a quote request. Only published listings
 * with an active owner can answer, so only they are chosen. Closest first
 * (same area, then same municipality), verified before unverified, and —
 * among equals — the businesses that received the fewest requests lately,
 * so the same few are not asked every time.
 */
final class QuoteMatcher
{
    public static function maxRecipients(): int
    {
        return max(1, min(10, (int) setting('quotes.max_recipients', 5)));
    }

    /** @return Collection<int, Business> */
    public function match(Category $category, ?Municipality $municipality = null, ?Area $area = null, ?Business $origin = null, ?int $excludeOwnerId = null, ?int $limit = null): Collection
    {
        $limit ??= self::maxRecipients();
        $chosen = collect();

        if ($origin && $this->eligible()->whereKey($origin->id)->when($excludeOwnerId, fn ($q) => $q->where('businesses.owner_id', '!=', $excludeOwnerId))->exists()) {
            $chosen->push($origin);
        }

        $query = $this->eligible()->inCategory($category)
            ->when($excludeOwnerId, fn ($q) => $q->where('businesses.owner_id', '!=', $excludeOwnerId))
            ->when($chosen->isNotEmpty(), fn ($q) => $q->whereKeyNot($origin->id))
            ->select('businesses.*');

        // Within an area or municipality when one is given, but fill up from the rest of Qatar if few are there.
        if ($area) {
            $query->orderByRaw('CASE WHEN businesses.area_id = ? THEN 0 ELSE 1 END', [$area->id]);
        }
        if ($municipality ?? $area?->municipality) {
            $query->orderByRaw('CASE WHEN businesses.municipality_id = ? THEN 0 ELSE 1 END', [($municipality ?? $area->municipality)->id]);
        }

        $recent = now()->subDays(30);
        $query->orderByDesc('businesses.is_verified')
            ->orderByRaw('(SELECT COUNT(*) FROM quote_request_recipients r WHERE r.business_id = businesses.id AND r.created_at >= ?) ASC', [$recent])
            ->orderByDesc('businesses.rating_avg')
            ->orderBy('businesses.id');

        return $chosen->concat($query->limit($limit - $chosen->count())->get())->values();
    }

    /** How many businesses would receive the request (for the live counter on the form). */
    public function count(Category $category, ?Municipality $municipality = null, ?Area $area = null, ?Business $origin = null, ?int $excludeOwnerId = null): int
    {
        return $this->match($category, $municipality, $area, $origin, $excludeOwnerId)->count();
    }

    /** Whether a single listing can receive requests at all. */
    public function canReceive(Business $business): bool
    {
        return $this->eligible()->whereKey($business->id)->exists();
    }

    private function eligible(): Builder
    {
        return Business::query()->published()
            ->whereNotNull('businesses.owner_id')
            ->whereExists(fn ($s) => $s->selectRaw('1')->from('users')
                ->whereColumn('users.id', 'businesses.owner_id')
                ->where('users.status', UserStatus::Active->value)
                ->whereNull('users.deleted_at'));
    }
}
