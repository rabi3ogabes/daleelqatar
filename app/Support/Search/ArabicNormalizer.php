<?php

declare(strict_types=1);

namespace App\Support\Search;

/**
 * Normalises Arabic (and Latin) text so that common spelling variants match:
 * أ إ آ ٱ → ا, ى → ي, ة → ه, and tashkeel / tatweel are removed.
 *
 * The PHP and SQL implementations MUST stay identical, because the SQL
 * version builds the stored search columns and the PHP version normalises
 * user queries before they are compared against those columns.
 */
final class ArabicNormalizer
{
    /** Characters replaced one-to-one (first six) or deleted (the rest). */
    public const FROM = 'أإآٱىةـًٌٍَُِّْ';

    public const TO = 'اااايه';

    public static function normalize(?string $text): string
    {
        $text = mb_strtolower(trim((string) $text));
        $from = mb_str_split(self::FROM);
        $to = mb_str_split(self::TO);
        $map = [];
        foreach ($from as $i => $char) {
            $map[$char] = $to[$i] ?? '';
        }
        $text = strtr($text, $map);

        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }

    public static function sql(string $expression): string
    {
        return sprintf("translate(lower(%s), '%s', '%s')", $expression, self::FROM, self::TO);
    }
}
