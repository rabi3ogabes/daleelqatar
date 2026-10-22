<?php

declare(strict_types=1);

namespace App\Support\Discovery;

use App\Support\Import\Normalizer;
use App\Support\Import\RecordBuilder;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Str;

/**
 * Reads a company from a directory page that has no structured data, the way
 * a person would: it finds the block that holds the company's own name next
 * to its phone or e-mail links (not the directory's "Contact seller" or footer
 * box), then reads the details inside it, the description section, the main
 * photo (often a scanned business card) and a logo when there is a real one.
 */
final class SmartReader
{
    /** Headings of boxes that describe the directory itself, never the company. */
    private const GENERIC_HEADINGS = '/^(contact( us| seller| the seller| owner| info(rmation)?| details)?|get in touch|send (us )?a message|reach us|our (office|address)|quick links|newsletter|follow us|listing details|claim (this )?business|اتصل بنا|تواصل معنا|معلومات الاتصال)$/iu';

    private const CHROME = 'ancestor-or-self::*[self::header or self::footer or self::nav or self::aside or contains(concat(" ", normalize-space(@class), " "), " modal ") or contains(@class, "cookie") or contains(@class, "footer") or contains(@class, "navbar") or contains(@id, "footer") or contains(@id, "header")]';

    private const LABELS = [
        'category' => '/^\s*(categor(y|ies)|industry|business type|activity|التصنيف|الفئة|النشاط)\s*[:：\-–]\s*(.+)$/iu',
        'address' => '/^\s*(address|location|الموقع|العنوان)\s*[:：\-–]\s*(.+)$/iu',
        'po_box' => '/^\s*(p\.?\s*o\.?\s*box|po box|ص\.?\s*ب)\s*[:：\-–]?\s*(\d{2,7})\s*$/iu',
    ];

    /**
     * @return array<string, string> raw fields as RecordBuilder::build() expects (plus logo / image URLs)
     */
    public static function read(DOMXPath $xpath, string $pageUrl): array
    {
        $container = self::companyBlock($xpath, $pageUrl);
        if ($container === null) {
            return [];
        }

        [$block, $heading] = $container;
        $out = ['name' => $heading];
        if (Normalizer::hasArabic($heading) && ! preg_match('/[A-Za-z]{3}/', $heading)) {
            $out = ['name_ar' => $heading];
        }

        $phones = [];
        $emails = [];
        $websites = [];
        $social = [];
        foreach ($xpath->query('.//a[@href]', $block) ?: [] as $a) {
            /** @var DOMElement $a */
            $href = trim($a->getAttribute('href'));
            $lower = Str::lower($href);
            if (str_starts_with($lower, 'tel:')) {
                $phones[] = trim(rawurldecode(substr($href, 4)));
            } elseif (str_starts_with($lower, 'mailto:')) {
                $emails[] = (string) preg_replace('/\?.*$/', '', rawurldecode(substr($href, 7)));
            } elseif (preg_match('#^https?://(wa\.me|api\.whatsapp\.com)/#i', $href)) {
                $out['whatsapp'] = $href;
            } elseif (self::isMapLink($href)) {
                [$lat, $lng] = self::coordinates($href);
                if ($lat !== null) {
                    [$out['latitude'], $out['longitude']] = [$lat, $lng];
                }
                $text = self::text($a);
                if ($text !== '' && ! preg_match('/^(map|view (on )?map|directions|get directions|الخريطة)$/iu', $text)) {
                    $out['address'] ??= $text;
                }
            } elseif (preg_match('#^https?://#i', $href) && ! UrlGuard::sameSite($href, $pageUrl)) {
                RecordBuilder::platformFor($href) !== null ? $social[] = $href : $websites[] = $href;
            }
        }

        // Labelled lines ("Category : BUILDING MATERIALS", "Address: …", "P.O. Box 123") and plain phone numbers.
        foreach ($xpath->query('.//p | .//li | .//span | .//div[not(*)] | .//dd', $block) ?: [] as $node) {
            $text = self::text($node);
            if ($text === '' || mb_strlen($text) > 300) {
                continue;
            }
            foreach (self::LABELS as $field => $pattern) {
                if (! isset($out[$field]) && preg_match($pattern, $text, $m)) {
                    $out[$field] = trim(end($m));
                }
            }
            if ($xpath->query('.//a[starts-with(@href, "tel:")]', $node)->length === 0 && preg_match_all('/(?:\+?974[\s-]?)?\b[3-7]\d{3}[\s-]?\d{4}\b/', $text, $m)) {
                array_push($phones, ...$m[0]);
            }
            if (! isset($out['address']) && $node instanceof DOMElement && self::looksLikeAddress($xpath, $node)) {
                $out['address'] = $text;
            }
        }

        // Coordinates on the block itself (data-lat / data-lng), when the link had none.
        if (! isset($out['latitude'])) {
            foreach ($xpath->query('.//*[@data-lat or @data-latitude]', $block) ?: [] as $node) {
                /** @var DOMElement $node */
                $lat = $node->getAttribute('data-lat') ?: $node->getAttribute('data-latitude');
                $lng = $node->getAttribute('data-lng') ?: ($node->getAttribute('data-long') ?: $node->getAttribute('data-longitude'));
                if (is_numeric($lat) && is_numeric($lng)) {
                    [$out['latitude'], $out['longitude']] = [$lat, $lng];
                    break;
                }
            }
        }

        $out['phone'] = implode("\n", array_unique(array_filter($phones)));
        $out['email'] = implode("\n", array_unique(array_filter($emails, fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL) !== false)));
        $out['website'] = $websites[0] ?? '';
        $out['social'] = implode("\n", array_unique($social));
        $out['description'] = self::description($xpath);
        $out['image'] = self::mainImage($xpath, $pageUrl) ?? '';
        $out['logo'] = self::logo($xpath, $block, $pageUrl) ?? '';

        return array_filter(array_map(fn ($v) => is_string($v) ? trim($v) : $v, $out), fn ($v) => $v !== '' && $v !== null);
    }

    /**
     * The element that holds the company's own name and its contact links.
     *
     * @return array{0: DOMElement, 1: string}|null
     */
    public static function companyBlock(DOMXPath $xpath, string $pageUrl): ?array
    {
        $reference = self::pageTitle($xpath);
        $candidates = [];
        $anchors = $xpath->query('//a[starts-with(translate(@href, "TELMAIO", "telmaio"), "tel:") or starts-with(translate(@href, "TELMAIO", "telmaio"), "mailto:")]') ?: [];

        foreach ($anchors as $anchor) {
            if ($xpath->query(self::CHROME, $anchor)->length > 0) {
                continue;
            }
            // Walk up to the nearest element that also has a heading.
            $node = $anchor->parentNode;
            for ($depth = 0; $node instanceof DOMElement && $depth < 6; $depth++, $node = $node->parentNode) {
                $heading = $xpath->query('(.//h1 | .//h2 | .//h3 | .//h4 | .//h5 | .//*[contains(concat(" ", normalize-space(@class), " "), " title ")])[1]', $node)->item(0);
                if ($heading === null) {
                    continue;
                }
                $text = self::text($heading);
                if ($text === '' || mb_strlen($text) > 160 || preg_match(self::GENERIC_HEADINGS, $text)) {
                    break; // the directory's own contact box
                }
                $key = spl_object_id($node);
                $candidates[$key] ??= ['node' => $node, 'heading' => $text, 'score' => 0.0];
                break;
            }
        }
        if ($candidates === []) {
            return null;
        }

        // Prefer the block whose heading is the page's subject (og:title / <title> start with it).
        foreach ($candidates as $key => $c) {
            $score = $reference !== '' ? Normalizer::nameSimilarity($c['heading'], Str::before($reference, ' – ')) : 0.0;
            if ($reference !== '' && Str::startsWith(Str::lower($reference), Str::lower($c['heading']))) {
                $score += 1.0;
            }
            $score += $xpath->query('self::*[.//h1]', $c['node'])->length > 0 ? 0.3 : 0.0;
            $candidates[$key]['score'] = $score;
        }
        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);
        $best = $candidates[0];

        return [$best['node'], $best['heading']];
    }

    /** @return array{0: ?string, 1: ?string} */
    public static function coordinates(string $href): array
    {
        $href = rawurldecode($href);
        foreach ([
            '/[?&](?:q|query|ll|center|destination|daddr)=(-?\d{1,2}\.\d{3,}),\s*(-?\d{1,3}\.\d{3,})/',
            '/@(-?\d{1,2}\.\d{3,}),(-?\d{1,3}\.\d{3,})/',
            '/!3d(-?\d{1,2}\.\d{3,})!4d(-?\d{1,3}\.\d{3,})/',
        ] as $pattern) {
            if (preg_match($pattern, $href, $m)) {
                return [$m[1], $m[2]];
            }
        }

        return [null, null];
    }

    public static function isMapLink(string $href): bool
    {
        return (bool) preg_match('#^https?://(www\.)?(google\.[a-z.]+/maps|maps\.google\.[a-z.]+|goo\.gl/maps|maps\.app\.goo\.gl|www\.openstreetmap\.org|maps\.apple\.com)#i', $href);
    }

    /** The page's own subject: og:title / title meta (also the ":content" form some JavaScript sites use), else <title>. */
    public static function pageTitle(DOMXPath $xpath): string
    {
        foreach (['og:title', 'title', 'twitter:title'] as $name) {
            if (($value = self::meta($xpath, $name)) !== '') {
                return $value;
            }
        }

        return self::text($xpath->query('//title')->item(0));
    }

    public static function meta(DOMXPath $xpath, string $name): string
    {
        $node = $xpath->query('//meta[@property="'.$name.'" or @name="'.$name.'"]')->item(0);
        if (! $node instanceof DOMElement) {
            return '';
        }
        $value = $node->getAttribute('content');
        if ($value === '' && $node->hasAttribute(':content')) {
            $value = trim($node->getAttribute(':content'), " '\"");
        }

        return trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5));
    }

    private static function description(DOMXPath $xpath): string
    {
        $box = $xpath->query('//*[@id="description" or contains(concat(" ", normalize-space(@class), " "), " description-box ") or contains(concat(" ", normalize-space(@class), " "), " listing-description ") or contains(@class, "about-company")]')->item(0);
        if ($box !== null && $xpath->query(self::CHROME, $box)->length === 0) {
            $text = trim((string) preg_replace('/^(description|about( us)?|overview|الوصف|نبذة)\s*/iu', '', self::text($box)));
            if (mb_strlen($text) >= 20) {
                return Str::limit($text, 4000, '');
            }
        }

        return self::meta($xpath, 'og:description') ?: self::meta($xpath, 'description');
    }

    /** The listing's main photo — on business-card directories, the scanned card itself. */
    private static function mainImage(DOMXPath $xpath, string $pageUrl): ?string
    {
        $query = '//*[contains(@class, "carousel") or contains(@class, "gallery") or contains(@class, "slider") or @data-fancybox]//img | //*[@data-fancybox][@data-src]';
        foreach ($xpath->query($query) ?: [] as $node) {
            /** @var DOMElement $node */
            if ($xpath->query(self::CHROME, $node)->length > 0) {
                continue;
            }
            $src = $node->tagName === 'img' ? ($node->getAttribute('src') ?: $node->getAttribute('data-src')) : $node->getAttribute('data-src');
            $alt = Str::lower($node->getAttribute('alt').' '.$node->getAttribute('class').' '.$src);
            if ($src === '' || str_starts_with($src, 'data:') || preg_match('/\b(ad|ads|advert\w*|banner|cookie|logo|avatar|icon)\b|_ad_|ad_image/', $alt)) {
                continue;
            }
            if (($url = Urls::resolve($pageUrl, $src)) !== null) {
                return $url;
            }
        }

        return null;
    }

    /** A company logo next to its name — never the directory's own logo (header / footer). */
    private static function logo(DOMXPath $xpath, DOMElement $block, string $pageUrl): ?string
    {
        $scope = $block->parentNode instanceof DOMElement ? $block->parentNode : $block;
        foreach ($xpath->query('.//img[contains(translate(concat(@class, " ", @alt, " ", @src), "LOGO", "logo"), "logo")]', $scope) ?: [] as $img) {
            /** @var DOMElement $img */
            if ($xpath->query(self::CHROME, $img)->length > 0) {
                continue;
            }
            $src = $img->getAttribute('src') ?: $img->getAttribute('data-src');
            if ($src !== '' && ! str_starts_with($src, 'data:') && ($url = Urls::resolve($pageUrl, $src)) !== null && ! self::isSiteLogo($xpath, $url, $pageUrl)) {
                return $url;
            }
        }

        return null;
    }

    private static function isSiteLogo(DOMXPath $xpath, string $url, string $pageUrl): bool
    {
        foreach ($xpath->query('//header//img | //nav//img | //footer//img | //link[contains(@rel, "icon")]') ?: [] as $node) {
            /** @var DOMElement $node */
            $src = $node->getAttribute('src') ?: $node->getAttribute('href');
            if ($src !== '' && Urls::resolve($pageUrl, $src) === $url) {
                return true;
            }
        }

        return false;
    }

    private static function looksLikeAddress(DOMXPath $xpath, DOMElement $node): bool
    {
        $marker = $xpath->query('.//i[contains(@class, "map-marker") or contains(@class, "location")] | self::*[contains(@class, "address") or contains(@class, "location")]', $node)->length > 0;

        return $marker && $xpath->query('.//a[starts-with(@href, "tel:") or starts-with(@href, "mailto:")]', $node)->length === 0;
    }

    private static function text(?DOMNode $node): string
    {
        return $node === null ? '' : trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($node->textContent, ENT_QUOTES | ENT_HTML5)));
    }
}
