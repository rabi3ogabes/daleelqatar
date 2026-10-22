<?php

declare(strict_types=1);

namespace App\Support\Enrichment;

use App\Models\Business;
use App\Support\Discovery\DiscoveryException;
use App\Support\Discovery\Fetcher;
use App\Support\Discovery\FetchResult;
use App\Support\Discovery\Urls;
use DOMDocument;
use DOMElement;
use DOMXPath;
use GdImage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Looks for a company's real logo on its own website — the listing's website
 * or the domain of its e-mail (info@company.qa → company.qa) — through the
 * crawler's polite fetcher (robots.txt, rate limits, private-network guard).
 *
 * Nothing is saved here: candidates are cleaned up (trimmed, padded, put on a
 * dark plate when the artwork is white) and held briefly in the cache until an
 * admin picks one.
 */
final class LogoFinder
{
    public const SIZE = 600;

    private const MAX_SITES = 2;

    private const MAX_DOWNLOADS = 8;

    private const MAX_SVG_BYTES = 400_000;

    private const CACHE_MINUTES = 30;

    /** Seconds of fetching before the search stops and shows what it has (the request limit is 60). */
    private const BUDGET_SECONDS = 25;

    /** Higher is better; the order a person would trust a source in. */
    private const SOURCE_RANK = ['schema' => 100, 'header' => 90, 'inline' => 75, 'page' => 70, 'touch_icon' => 55, 'icon' => 45, 'social' => 30];

    private readonly Fetcher $fetcher;

    public function __construct(private readonly WebsiteReader $reader, int $delayMs = 250)
    {
        $this->fetcher = new Fetcher($delayMs, 8);
    }

    /**
     * @return array{sites: list<array{url: string, source: string, reachable: bool}>, candidates: list<array<string, mixed>>}
     */
    public function find(Business $business): array
    {
        $sites = [];
        $found = [];
        $downloads = 0;
        $seen = [];
        $deadline = microtime(true) + self::BUDGET_SECONDS;

        foreach (array_slice($this->reader->candidates($business), 0, self::MAX_SITES) as $site) {
            if (microtime(true) > $deadline) {
                break;
            }
            $home = $this->home($site['url']);
            $sites[] = ['url' => $home?->url ?? $site['url'], 'source' => $site['source'], 'reachable' => $home !== null];
            if ($home === null) {
                continue;
            }

            foreach ($this->references($home) as $ref) {
                if ($downloads >= self::MAX_DOWNLOADS || count($found) >= 6 || microtime(true) > $deadline) {
                    break 2;
                }
                if ($ref['svg'] !== null) {
                    $candidate = $this->fromSvg($ref['svg'], $ref['source'], $home->url);
                } else {
                    if (isset($seen[$ref['url']])) {
                        continue;
                    }
                    $seen[$ref['url']] = true;
                    $downloads++;
                    $candidate = $this->download($ref['url'], $ref['source']);
                }
                if ($candidate !== null && ! in_array($candidate['hash'], array_column($found, 'hash'), true)) {
                    $found[] = $candidate;
                }
            }
        }

        usort($found, fn ($a, $b) => $b['score'] <=> $a['score']);

        $candidates = [];
        foreach ($found as $candidate) {
            $id = Str::lower(Str::random(20));
            Cache::put($this->key($business, $id), ['kind' => $candidate['kind'], 'bytes' => base64_encode($candidate['bytes']), 'url' => $candidate['url']], now()->addMinutes(self::CACHE_MINUTES));
            $candidates[] = [
                'id' => $id,
                'kind' => $candidate['kind'],
                'source' => $candidate['source'],
                'url' => $candidate['url'],
                'width' => $candidate['width'],
                'height' => $candidate['height'],
                'dark' => $candidate['dark'],
                'preview' => $candidate['preview'],
            ];
        }

        return ['sites' => $sites, 'candidates' => $candidates];
    }

    /** @return array{kind: string, bytes: string, url: ?string}|null */
    public function pull(Business $business, string $id): ?array
    {
        $cached = Cache::get($this->key($business, $id));
        if (! is_array($cached)) {
            return null;
        }

        return ['kind' => (string) $cached['kind'], 'bytes' => (string) base64_decode((string) $cached['bytes'], true), 'url' => $cached['url'] ?? null];
    }

    /**
     * Trims empty margins, fits the artwork in a padded square and — when the
     * artwork is white or very light on a transparent background — places it on
     * the brand's navy so it stays visible on white cards. Returns PNG bytes.
     *
     * @return array{png: string, dark: bool}|null
     */
    public static function process(string $bytes): ?array
    {
        $info = @getimagesizefromstring($bytes);
        if ($info === false || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
            return null;
        }
        if ($info[0] * $info[1] > 25_000_000) {
            return null;
        }
        $image = @imagecreatefromstring($bytes);
        if (! $image instanceof GdImage) {
            return null;
        }
        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);

        $stats = self::analyse($image);
        $trimmed = $stats['transparent'] > 0.02 ? @imagecropauto($image, IMG_CROP_TRANSPARENT) : @imagecropauto($image, IMG_CROP_SIDES);
        if ($trimmed instanceof GdImage && imagesx($trimmed) >= 8 && imagesy($trimmed) >= 8) {
            $image = $trimmed;
        }

        [$w, $h] = [imagesx($image), imagesy($image)];
        $dark = $stats['transparent'] > 0.15 && $stats['luminance'] > 0.82;
        $opaqueSquare = $stats['transparent'] <= 0.02 && abs($w - $h) <= max($w, $h) * 0.04;
        // Square app icons already carry their own background: fill the frame. Everything else gets a margin.
        $inner = $opaqueSquare ? self::SIZE : (int) round(self::SIZE * 0.84);
        $scale = min($inner / $w, $inner / $h, $opaqueSquare ? INF : 4.0);
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $canvas = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $background = $dark
            ? imagecolorallocatealpha($canvas, 0x10, 0x1C, 0x32, 0)
            : ($stats['transparent'] > 0.02 ? imagecolorallocatealpha($canvas, 255, 255, 255, 127) : self::cornerColor($image, $canvas));
        imagefilledrectangle($canvas, 0, 0, self::SIZE - 1, self::SIZE - 1, $background);
        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $image, intdiv(self::SIZE - $nw, 2), intdiv(self::SIZE - $nh, 2), 0, 0, $nw, $nh, $w, $h);
        imagealphablending($canvas, false);

        ob_start();
        imagepng($canvas, null, 6);
        $png = (string) ob_get_clean();

        return ['png' => $png, 'dark' => $dark];
    }

    private function key(Business $business, string $id): string
    {
        return 'logo-candidate:'.$business->id.':'.$id;
    }

    private function home(string $url): ?FetchResult
    {
        $tries = array_unique(array_filter([$url, preg_replace('#^(https?://)(?!www\.)#i', '$1www.', $url), preg_replace('#^https://#i', 'http://', $url)]));
        foreach ($tries as $try) {
            try {
                $result = $this->fetcher->get($try);
                if ($result->isHtml()) {
                    return $result;
                }
            } catch (DiscoveryException) {
                continue;
            }
        }

        return null;
    }

    /**
     * Logo references on a home page, best source first.
     *
     * @return list<array{url: string, source: string, svg: ?string}>
     */
    private function references(FetchResult $home): array
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">'.$home->body, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($doc);
        $base = $xpath->query('//base/@href')->item(0)?->nodeValue;
        $base = $base ? (Urls::resolve($home->url, $base) ?? $home->url) : $home->url;

        $refs = [];
        $add = function (?string $href, string $source) use (&$refs, $base) {
            $href = trim((string) $href);
            if ($href === '' || str_starts_with($href, 'data:')) {
                return;
            }
            $url = Urls::resolve($base, html_entity_decode($href, ENT_QUOTES | ENT_HTML5));
            if ($url !== null && preg_match('#^https?://#i', $url) && ! preg_match('/\.ico(\?|$)/i', $url)) {
                $refs[] = ['url' => $url, 'source' => $source, 'svg' => null];
            }
        };

        // 1. Structured data: { "@type": "Organization", "logo": "…" }.
        foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
            $data = json_decode(trim($script->textContent), true);
            if (is_array($data)) {
                foreach ($this->jsonLogos($data) as $logo) {
                    $add($logo, 'schema');
                }
            }
        }

        // 2. Images that call themselves a logo, those in the header/navigation first.
        $logoish = 'contains(translate(concat(@class," ",@id," ",@alt," ",@src," ",@data-src),"LOGO","logo"),"logo")';
        $parentLogo = 'ancestor::*[contains(translate(concat(@class," ",@id),"LOGO","logo"),"logo") or contains(@class,"brand")]';
        foreach (["//header//img[{$logoish} or {$parentLogo}]", "//nav//img[{$logoish} or {$parentLogo}]", "//img[{$logoish} or {$parentLogo}]"] as $i => $query) {
            foreach ($xpath->query($query) as $img) {
                /** @var DOMElement $img */
                $add($this->imgSource($img), $i < 2 ? 'header' : 'page');
            }
        }

        // 3. An inline <svg> logo inside the header's logo link.
        foreach ($xpath->query("(//header|//nav)//*[{$logoish} or contains(@class,'brand')]//svg | (//header|//nav)//svg[{$logoish}]") as $svg) {
            $markup = $doc->saveXML($svg);
            if ($markup && strlen($markup) > 200) {
                $refs[] = ['url' => $home->url, 'source' => 'inline', 'svg' => $markup];
                break;
            }
        }

        // 4. Touch icons and large favicons, then the social sharing image.
        $icons = [];
        foreach ($xpath->query('//link[@href][contains(@rel,"icon")]') as $link) {
            /** @var DOMElement $link */
            $rel = Str::lower($link->getAttribute('rel'));
            preg_match('/(\d+)x\d+/', $link->getAttribute('sizes'), $m);
            $size = (int) ($m[1] ?? (str_contains($rel, 'apple') ? 180 : 0));
            $isSvg = str_contains(Str::lower($link->getAttribute('type').$link->getAttribute('href')), 'svg');
            if ($size >= 96 || $isSvg) {
                $icons[] = [$size ?: 512, $link->getAttribute('href'), str_contains($rel, 'apple') ? 'touch_icon' : 'icon'];
            }
        }
        usort($icons, fn ($a, $b) => $b[0] <=> $a[0]);
        foreach ($icons as [, $href, $source]) {
            $add($href, $source);
        }
        $add($xpath->query('//meta[@name="msapplication-TileImage"]/@content')->item(0)?->nodeValue, 'icon');
        $add('/apple-touch-icon.png', 'touch_icon');
        $add($xpath->query('//meta[@property="og:logo"]/@content')->item(0)?->nodeValue, 'schema');
        $add($xpath->query('//meta[@property="og:image"]/@content')->item(0)?->nodeValue, 'social');

        // Keep the first (best) mention of each URL.
        $unique = [];
        foreach ($refs as $ref) {
            $key = $ref['svg'] !== null ? 'svg:'.md5($ref['svg']) : $ref['url'];
            $unique[$key] ??= $ref;
        }

        return array_values($unique);
    }

    /** @return list<string> */
    private function jsonLogos(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if ($key === 'logo') {
                $value = is_array($value) ? ($value['url'] ?? $value['contentUrl'] ?? ($value[0] ?? null)) : $value;
                if (is_string($value)) {
                    $out[] = $value;
                }
            } elseif (is_array($value)) {
                array_push($out, ...$this->jsonLogos($value));
            }
        }

        return $out;
    }

    private function imgSource(DOMElement $img): ?string
    {
        foreach (['src', 'data-src', 'data-lazy-src', 'data-original'] as $attr) {
            $value = trim($img->getAttribute($attr));
            if ($value !== '' && ! str_starts_with($value, 'data:')) {
                return $value;
            }
        }
        $srcset = $img->getAttribute('srcset') ?: $img->getAttribute('data-srcset');
        if ($srcset !== '') {
            $parts = array_map('trim', explode(',', $srcset));

            return explode(' ', (string) end($parts))[0] ?: null;
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function download(string $url, string $source): ?array
    {
        try {
            $result = $this->fetcher->get($url);
        } catch (DiscoveryException) {
            return null;
        }
        $body = $result->body;
        if ($body === '') {
            return null;
        }
        if (str_contains($result->contentType, 'svg') || preg_match('/^\s*(<\?xml[^>]*>\s*)?(<!--.*?-->\s*)*(<!DOCTYPE[^>]*>\s*)?<svg[\s>]/is', $body)) {
            return $this->fromSvg($body, $source, $url);
        }

        $info = @getimagesizefromstring($body);
        if ($info === false) {
            return null;
        }
        [$w, $h] = $info;
        // Too small to look sharp, or a wide banner rather than a mark.
        if (max($w, $h) < 96 || min($w, $h) < 24 || ($source === 'social' && max($w, $h) / max(1, min($w, $h)) > 2.2)) {
            return null;
        }
        $processed = self::process($body);
        if ($processed === null) {
            return null;
        }

        return [
            'kind' => 'raster',
            'source' => $source,
            'url' => $result->url,
            'bytes' => $processed['png'],
            'hash' => md5($body),
            'width' => $w,
            'height' => $h,
            'dark' => $processed['dark'],
            'score' => self::SOURCE_RANK[$source] + min(20, (int) (min($w, $h) / 20)),
            'preview' => 'data:image/png;base64,'.base64_encode(self::thumbnail($processed['png'])),
        ];
    }

    /**
     * SVG logos are drawn in the admin's browser (as an image, so scripts never
     * run) and sent back as a PNG; here they are only checked and sized.
     *
     * @return array<string, mixed>|null
     */
    private function fromSvg(string $svg, string $source, string $url): ?array
    {
        if (strlen($svg) > self::MAX_SVG_BYTES || ! preg_match('/<svg\b[^>]*>/is', $svg, $root)) {
            return null;
        }
        $svg = substr($svg, (int) strpos($svg, $root[0]));
        preg_match('/viewBox\s*=\s*["\']\s*[-\d.]+[\s,]+[-\d.]+[\s,]+([\d.]+)[\s,]+([\d.]+)/i', $root[0], $vb);
        preg_match('/\bwidth\s*=\s*["\']\s*([\d.]+)/i', $root[0], $wm);
        preg_match('/\bheight\s*=\s*["\']\s*([\d.]+)/i', $root[0], $hm);
        $w = (float) ($vb[1] ?? $wm[1] ?? 0);
        $h = (float) ($vb[2] ?? $hm[1] ?? 0);
        if ($w <= 0 || $h <= 0) {
            return null;
        }

        // Give the root an explicit size and namespace so browsers render it predictably as an image.
        $scale = 1200 / max($w, $h);
        $tag = preg_replace('/\s(width|height)\s*=\s*(["\']).*?\2/i', '', $root[0]) ?? $root[0];
        if (! str_contains($tag, 'xmlns=')) {
            $tag = preg_replace('/^<svg\b/i', '<svg xmlns="http://www.w3.org/2000/svg"', $tag) ?? $tag;
        }
        $tag = preg_replace('/^<svg\b/i', sprintf('<svg width="%d" height="%d"', (int) round($w * $scale), (int) round($h * $scale)), $tag) ?? $tag;
        $svg = $tag.substr($svg, strlen($root[0]));
        if (! str_contains($tag, 'viewBox')) {
            return null;
        }

        return [
            'kind' => 'svg',
            'source' => $source,
            'url' => $url,
            'bytes' => $svg,
            'hash' => md5($svg),
            'width' => (int) round($w),
            'height' => (int) round($h),
            'dark' => false,
            'score' => self::SOURCE_RANK[$source] + 15,
            'preview' => 'data:image/svg+xml;base64,'.base64_encode($svg),
        ];
    }

    /** @return array{transparent: float, luminance: float} */
    private static function analyse(GdImage $image): array
    {
        [$w, $h] = [imagesx($image), imagesy($image)];
        $step = max(1, (int) floor(sqrt($w * $h / 4000)));
        $samples = $transparent = 0;
        $luminance = 0.0;
        $opaque = 0;
        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                $samples++;
                if ($alpha > 100) {
                    $transparent++;

                    continue;
                }
                $opaque++;
                $luminance += (0.2126 * (($rgba >> 16) & 0xFF) + 0.7152 * (($rgba >> 8) & 0xFF) + 0.0722 * ($rgba & 0xFF)) / 255;
            }
        }

        return ['transparent' => $samples ? $transparent / $samples : 0.0, 'luminance' => $opaque ? $luminance / $opaque : 0.0];
    }

    private static function cornerColor(GdImage $source, GdImage $canvas): int
    {
        $rgba = imagecolorat($source, 0, 0);

        return imagecolorallocatealpha($canvas, ($rgba >> 16) & 0xFF, ($rgba >> 8) & 0xFF, $rgba & 0xFF, 0);
    }

    private static function thumbnail(string $png): string
    {
        $image = imagecreatefromstring($png);
        $thumb = imagecreatetruecolor(240, 240);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, 240, 240, imagesx($image), imagesy($image));
        ob_start();
        imagepng($thumb, null, 7);

        return (string) ob_get_clean();
    }
}
