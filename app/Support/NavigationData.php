<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Category;
use App\Models\Municipality;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/** Cached taxonomy used by navigation, footer and filters (refreshed every 10 minutes or on change). */
final class NavigationData
{
    public const CACHE_KEYS = ['nav.categories', 'nav.municipalities'];

    /** @return Collection<int, Category> */
    public function rootCategories(): Collection
    {
        return Cache::remember('nav.categories', now()->addMinutes(10), fn () => Category::query()->active()->roots()->ordered()
            ->with(['children' => fn ($q) => $q->active()])->get());
    }

    /** @return Collection<int, Municipality> */
    public function municipalities(): Collection
    {
        return Cache::remember('nav.municipalities', now()->addMinutes(10), fn () => Municipality::query()->active()->ordered()
            ->with(['areas' => fn ($q) => $q->active()])->get());
    }

    public function footerCategories(): Collection
    {
        return $this->rootCategories()->where('is_featured', true)->take(6);
    }

    public function footerLocations(): Collection
    {
        return $this->municipalities()->take(6);
    }

    /** Flat [{id, m, name}] list for dependent area selects. */
    public function areaOptions(): array
    {
        return $this->municipalities()->flatMap(fn (Municipality $m) => $m->areas->map(fn ($a) => [
            'id' => $a->id, 'm' => $m->id, 'slug' => $a->slug, 'name' => $a->t('name'),
        ]))->values()->all();
    }

    public static function flush(): void
    {
        foreach (self::CACHE_KEYS as $key) {
            Cache::forget($key);
        }
    }
}
