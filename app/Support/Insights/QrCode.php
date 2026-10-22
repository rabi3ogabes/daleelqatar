<?php

declare(strict_types=1);

namespace App\Support\Insights;

use App\Models\Business;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Eye\SquareEye;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Module\RoundnessModule;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** QR codes for the printable poster, drawn as SVG in the brand's navy. */
final class QrCode
{
    /** The short, permanent address printed on posters; scans are counted there. */
    public static function url(Business $business): string
    {
        return route('qr.scan', $business->slug);
    }

    public static function svg(string $data, int $size = 480): string
    {
        $style = new RendererStyle(
            $size,
            1,
            new RoundnessModule(RoundnessModule::SOFT),
            SquareEye::instance(),
            Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(16, 28, 50)),
        );
        $svg = (new Writer(new ImageRenderer($style, new SvgImageBackEnd)))
            ->writeString($data, 'UTF-8', ErrorCorrectionLevel::Q());

        // Inline-ready: no XML prolog, and it scales with its container.
        $svg = (string) preg_replace('/^<\?xml[^>]*>\s*/', '', $svg);

        return (string) preg_replace('/<svg /', '<svg role="img" preserveAspectRatio="xMidYMid meet" ', $svg, 1);
    }
}
