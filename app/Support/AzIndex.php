<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Alphabetical index of published businesses. Arabic pages sort by the Arabic
 * name (falling back to the English one) and English pages the other way round,
 * so every listing is reachable from a crawlable letter page.
 */
final class AzIndex
{
    public const OTHER = '0-9';

    /** Letter => first characters grouped under it (hamza forms under alif, etc.). */
    public const ARABIC = [
        'ا' => ['ا', 'أ', 'إ', 'آ', 'ء'], 'ب' => ['ب'], 'ت' => ['ت'], 'ث' => ['ث'], 'ج' => ['ج'], 'ح' => ['ح'], 'خ' => ['خ'],
        'د' => ['د'], 'ذ' => ['ذ'], 'ر' => ['ر'], 'ز' => ['ز'], 'س' => ['س'], 'ش' => ['ش'], 'ص' => ['ص'], 'ض' => ['ض'],
        'ط' => ['ط'], 'ظ' => ['ظ'], 'ع' => ['ع'], 'غ' => ['غ'], 'ف' => ['ف'], 'ق' => ['ق'], 'ك' => ['ك'], 'ل' => ['ل'],
        'م' => ['م'], 'ن' => ['ن'], 'ه' => ['ه', 'ة'], 'و' => ['و', 'ؤ'], 'ي' => ['ي', 'ى', 'ئ'],
    ];

    /** @return list<string> */
    public static function latin(): array
    {
        return range('A', 'Z');
    }

    public static function isLetter(string $letter): bool
    {
        return $letter === self::OTHER || isset(self::ARABIC[$letter]) || in_array($letter, self::latin(), true);
    }

    public static function isArabic(string $letter): bool
    {
        return isset(self::ARABIC[$letter]);
    }

    /** SQL expression for the name a locale sorts by. */
    public static function nameExpression(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar'
            ? "COALESCE(NULLIF(TRIM(businesses.name_ar), ''), TRIM(businesses.name_en))"
            : "COALESCE(NULLIF(TRIM(businesses.name_en), ''), TRIM(businesses.name_ar))";
    }

    /** @return array<string,int> letter => number of published listings */
    public static function counts(?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        return Cache::remember('az.counts.'.$locale, now()->addMinutes(30), function () use ($locale) {
            $raw = DB::table('businesses')
                ->where('status', 'approved')->whereNotNull('published_at')->whereNull('deleted_at')
                ->selectRaw('UPPER(LEFT('.self::nameExpression($locale).', 1)) AS initial, COUNT(*) AS total')
                ->groupBy('initial')->pluck('total', 'initial');

            $lookup = self::lookup();
            $counts = [];
            foreach ($raw as $initial => $total) {
                $letter = $lookup[(string) $initial] ?? self::OTHER;
                $counts[$letter] = ($counts[$letter] ?? 0) + (int) $total;
            }

            return $counts;
        });
    }

    public static function scope(Builder $query, string $letter, ?string $locale = null): Builder
    {
        $expression = 'UPPER(LEFT('.self::nameExpression($locale).', 1))';

        if ($letter === self::OTHER) {
            $all = array_keys(self::lookup());

            return $query->whereRaw($expression.' NOT IN ('.implode(',', array_fill(0, count($all), '?')).')', $all);
        }

        $initials = self::ARABIC[$letter] ?? [$letter];

        return $query->whereRaw($expression.' IN ('.implode(',', array_fill(0, count($initials), '?')).')', $initials);
    }

    /** @return Builder<Business> */
    public static function query(string $letter, ?string $locale = null): Builder
    {
        return self::scope(Business::query()->published(), $letter, $locale)
            ->orderByRaw(self::nameExpression($locale))->orderBy('businesses.id');
    }

    public static function flush(): void
    {
        foreach (Localization::LOCALES as $locale) {
            Cache::forget('az.counts.'.$locale);
        }
    }

    /** @return array<string,string> first character => letter */
    private static function lookup(): array
    {
        $map = [];
        foreach (self::ARABIC as $letter => $initials) {
            foreach ($initials as $initial) {
                $map[$initial] = $letter;
            }
        }
        foreach (self::latin() as $letter) {
            $map[$letter] = $letter;
        }

        return $map;
    }
}
