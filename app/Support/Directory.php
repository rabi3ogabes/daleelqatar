<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\BusinessStatus;
use App\Models\SeasonalPeriod;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Shared read-side helpers for public directory pages. */
final class Directory
{
    /** Relations eager-loaded for every business card (prevents N+1 queries). */
    public static function cardRelations(): array
    {
        return [
            'category:id,name_ar,name_en,slug,icon',
            'municipality:id,name_ar,name_en,slug',
            'area:id,name_ar,name_en,slug,municipality_id',
            'openingHours',
            'images' => fn ($q) => $q->limit(1),
            ...self::seasonRelation(),
        ];
    }

    /** Special hours of the season running today, eager-loaded so "open now" on cards needs no extra queries. */
    public static function seasonRelation(): array
    {
        $ids = SeasonalPeriod::current()->pluck('id')->all();

        return $ids ? ['specialHours' => fn ($q) => $q->whereIn('seasonal_period_id', $ids)] : [];
    }

    /** Published businesses per ROOT category (sub-category listings roll up). @return array<int,int> */
    public static function categoryCounts(): array
    {
        return Cache::remember('dir.category_counts', now()->addMinutes(5), fn () => DB::table('businesses')
            ->join('categories', 'categories.id', '=', 'businesses.category_id')
            ->where('businesses.status', BusinessStatus::Approved->value)
            ->whereNotNull('businesses.published_at')->whereNull('businesses.deleted_at')
            ->selectRaw('COALESCE(categories.parent_id, categories.id) AS root_id, COUNT(*) AS total')
            ->groupBy('root_id')->pluck('total', 'root_id')->map(fn ($v) => (int) $v)->all());
    }

    /** @return array<int,int> */
    public static function municipalityCounts(): array
    {
        return Cache::remember('dir.municipality_counts', now()->addMinutes(5), fn () => DB::table('businesses')
            ->where('status', BusinessStatus::Approved->value)->whereNotNull('published_at')->whereNull('deleted_at')
            ->whereNotNull('municipality_id')
            ->selectRaw('municipality_id, COUNT(*) AS total')->groupBy('municipality_id')
            ->pluck('total', 'municipality_id')->map(fn ($v) => (int) $v)->all());
    }

    public static function flushCounts(): void
    {
        foreach (['dir.category_counts', 'dir.municipality_counts', 'home.stats', 'home.featured_areas'] as $key) {
            Cache::forget($key);
        }
        AzIndex::flush();
    }

    /** @return list<int> IDs of the signed-in user's favourite businesses. */
    public static function favoriteIds(): array
    {
        $user = auth()->user();

        return $user ? DB::table('favorites')->where('user_id', $user->id)->pluck('business_id')->map(fn ($id) => (int) $id)->all() : [];
    }

    public static function organizationSchema(): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => __('brand.name'),
            'alternateName' => app()->getLocale() === 'ar' ? 'Daleel Qatar' : 'دليل قطر',
            'url' => route('home'),
            'logo' => asset('brand/icon-512.png'),
            'email' => setting('contact.email'),
            'sameAs' => array_values(array_filter(array_map(fn ($k) => safe_external_url(setting('social.'.$k)), ['instagram', 'x', 'facebook', 'linkedin', 'tiktok']))),
        ]);
    }
}
