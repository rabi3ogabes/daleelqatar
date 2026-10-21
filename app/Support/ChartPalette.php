<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Categorical chart palette derived from the brand (Gulf blue, deep gold, Qatar
 * maroon, teal, sand, violet). Validated for lightness band, chroma, colour-blind
 * separation (worst adjacent ΔE 9.3) and normal vision. Assigned in fixed order,
 * never cycled — extra categories fold into "Other".
 */
final class ChartPalette
{
    public const CATEGORICAL = ['#2E68B0', '#C48A1C', '#9B2246', '#16917F', '#E07B39', '#7262B8'];

    public const OTHER = '#9AA3B2';

    public const PRIMARY = '#A8843F';   // single-series line (gold-600)

    public const SUCCESS = '#1F7A52';

    public const DANGER = '#B42318';

    /** Nice axis maximum and tick step for a value range. @return array{0: float, 1: float} */
    public static function niceScale(float $max, int $ticks = 4): array
    {
        if ($max <= 0) {
            return [$ticks, 1];
        }
        $raw = $max / $ticks;
        $mag = 10 ** floor(log10($raw));
        $norm = $raw / $mag;
        $step = ($norm <= 1 ? 1 : ($norm <= 2 ? 2 : ($norm <= 2.5 ? 2.5 : ($norm <= 5 ? 5 : 10)))) * $mag;
        $step = max(1.0, (float) $step);

        return [ceil($max / $step) * $step, $step];
    }
}
