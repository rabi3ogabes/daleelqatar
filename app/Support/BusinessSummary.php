<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Business;

/**
 * A short, strictly factual description built from a listing's own data
 * (name, category, location, which contact options exist). Used when the
 * business has not written a description yet, so every page still has
 * meaningful, unique text for visitors and search engines — nothing is invented.
 */
final class BusinessSummary
{
    public static function for(Business $business, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $name = (string) $business->t('name', $locale);
        $category = $business->category?->t('name', $locale);
        $location = $business->locationLabel($locale);

        $sentences = [];
        $sentences[] = match (true) {
            $category && $location => __('directory.summary.category_location', ['name' => $name, 'category' => $category, 'location' => $location], $locale),
            (bool) $category => __('directory.summary.category', ['name' => $name, 'category' => $category], $locale),
            (bool) $location => __('directory.summary.location', ['name' => $name, 'location' => $location], $locale),
            default => __('directory.summary.name', ['name' => $name], $locale),
        };

        $channels = array_values(array_filter([
            $business->phone ? __('directory.summary.by_phone', [], $locale) : null,
            $business->whatsapp ? __('directory.summary.by_whatsapp', [], $locale) : null,
            $business->email ? __('directory.summary.by_email', [], $locale) : null,
            $business->website ? __('directory.summary.by_website', [], $locale) : null,
        ]));
        if ($channels !== []) {
            $sentences[] = __('directory.summary.contact', ['channels' => self::join($channels, $locale)], $locale);
        }

        $related = $business->relationLoaded('categories') ? $business->categories->map(fn ($c) => $c->t('name', $locale))->filter()->take(3)->values()->all() : [];
        if ($related !== []) {
            $sentences[] = __('directory.summary.also', ['categories' => self::join($related, $locale)], $locale);
        }

        return implode(' ', $sentences);
    }

    /** @param list<string> $items */
    private static function join(array $items, string $locale): string
    {
        if (count($items) <= 1) {
            return (string) ($items[0] ?? '');
        }
        $last = array_pop($items);

        return implode($locale === 'ar' ? '، ' : ', ', $items).' '.__('directory.summary.and', [], $locale).' '.$last;
    }
}
