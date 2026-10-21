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

    /**
     * SQL equivalent. Characters are emitted as chr(<codepoint>) so the generated
     * SQL is pure ASCII (portable across clients, dumps and migration tools).
     */
    public static function sql(string $expression): string
    {
        $chars = fn (string $s) => '('.implode('||', array_map(fn ($c) => 'chr('.mb_ord($c).')', mb_str_split($s))).')';

        return sprintf('translate(lower(%s), %s, %s)', $expression, $chars(self::FROM), $chars(self::TO));
    }
}
