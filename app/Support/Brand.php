<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The Daleel Qatar mark as inline SVG (a "D" holding the Doha skyline, rising
 * from the Gulf). Each render gets unique gradient ids so several logos can
 * share one page. Static files for every lockup live in public/brand/.
 */
final class Brand
{
    private const DEFS_LIGHT = '<linearGradient id="__ID__-g" x1="0" y1="0" x2="1" y2="1"> <stop offset="0" stop-color="#E3C88C"/> <stop offset="0.55" stop-color="#D7B875"/> <stop offset="1" stop-color="#A8843F"/> </linearGradient> <linearGradient id="__ID__-w" x1="0" y1="0" x2="1" y2="0"> <stop offset="0" stop-color="#A8843F"/> <stop offset="1" stop-color="#E3C88C"/> </linearGradient> <clipPath id="__ID__-m" clipPathUnits="userSpaceOnUse"> <path d="M0 0 H120 V80 L116 80.5 C 62 106.5, 32 76.5, 4 93.5 L0 96 Z"/> </clipPath>';

    private const DEFS_DARK = '<linearGradient id="__ID__-g" x1="0" y1="0" x2="1" y2="1"> <stop offset="0" stop-color="#F1DFB1"/> <stop offset="0.55" stop-color="#D7B875"/> <stop offset="1" stop-color="#C9A35E"/> </linearGradient> <linearGradient id="__ID__-w" x1="0" y1="0" x2="1" y2="0"> <stop offset="0" stop-color="#C9A35E"/> <stop offset="1" stop-color="#F1DFB1"/> </linearGradient> <clipPath id="__ID__-m" clipPathUnits="userSpaceOnUse"> <path d="M0 0 H120 V80 L116 80.5 C 62 106.5, 32 76.5, 4 93.5 L0 96 Z"/> </clipPath>';

    private const BODY_LIGHT = '<g clip-path="url(#__ID__-m)"> <path d="M18 8 H56 C88 8 110 30 110 60 C110 90 88 112 56 112 H18 Z M33 23 H56 C79 23 95 39 95 60 C95 81 79 97 56 97 H33 Z" fill-rule="evenodd" fill="url(#__ID__-g)"/> <path d="M38 100 V66 H45 V100 Z M48 100 V50 L52.5 38 L57 50 V100 Z M60 100 V46 C60 39.5 70 39.5 70 46 V100 Z M64.4 40.6 L65 33 L65.6 40.6 Z M73 96 C75.5 84 75.5 72 73 60 H81 C78.5 72 78.5 84 81 96 Z M84 100 V72 H89 V100 Z" fill="url(#__ID__-g)"/> </g> <path d="M4 101 C 32 84, 62 114, 116 88" fill="none" stroke="url(#__ID__-w)" stroke-width="6" stroke-linecap="round"/> <path d="M16 112 C 40 101, 66 120, 104 106" fill="none" stroke="#101C32" stroke-width="3.2" stroke-linecap="round" opacity="0.9"/>';

    private const BODY_DARK = '<g clip-path="url(#__ID__-m)"> <path d="M18 8 H56 C88 8 110 30 110 60 C110 90 88 112 56 112 H18 Z M33 23 H56 C79 23 95 39 95 60 C95 81 79 97 56 97 H33 Z" fill-rule="evenodd" fill="url(#__ID__-g)"/> <path d="M38 100 V66 H45 V100 Z M48 100 V50 L52.5 38 L57 50 V100 Z M60 100 V46 C60 39.5 70 39.5 70 46 V100 Z M64.4 40.6 L65 33 L65.6 40.6 Z M73 96 C75.5 84 75.5 72 73 60 H81 C78.5 72 78.5 84 81 96 Z M84 100 V72 H89 V100 Z" fill="url(#__ID__-g)"/> </g> <path d="M4 101 C 32 84, 62 114, 116 88" fill="none" stroke="url(#__ID__-w)" stroke-width="6" stroke-linecap="round"/> <path d="M16 112 C 40 101, 66 120, 104 106" fill="none" stroke="#F7F4ED" stroke-width="3.2" stroke-linecap="round" opacity="0.9"/>';

    public static function mark(string $variant = 'light', string $class = 'size-10', ?string $title = null): string
    {
        $id = 'dq'.Str::lower(Str::random(6));
        $dark = $variant === 'dark';
        $defs = str_replace('__ID__', $id, $dark ? self::DEFS_DARK : self::DEFS_LIGHT);
        $body = str_replace('__ID__', $id, $dark ? self::BODY_DARK : self::BODY_LIGHT);
        $label = $title !== null ? '<title>'.e($title).'</title>' : '';
        $aria = $title !== null ? 'role="img" aria-label="'.e($title).'"' : 'aria-hidden="true"';

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120" class="'.e($class).'" '.$aria.' focusable="false">'.$label.'<defs>'.$defs.'</defs>'.$body.'</svg>';
    }
}
