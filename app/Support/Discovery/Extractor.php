<?php

declare(strict_types=1);

namespace App\Support\Discovery;

use App\Support\Import\Normalizer;
use App\Support\Import\RecordBuilder;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Str;

/**
 * Reads company details from a directory's detail page, in this order of trust:
 *
 *  1. CSS selectors configured by an administrator for that site;
 *  2. schema.org JSON-LD (LocalBusiness / Organization and their subtypes);
 *  3. OpenGraph / <title> / <h1> — only to complete 1 or 2, never on their own,
 *     so listing pages and articles are not mistaken for companies.
 *
 * The output is the raw shape RecordBuilder::build() expects. Ratings, review
 * counts and "verified" badges are never read.
 */
final class Extractor
{
    public const FIELDS = ['name', 'name_ar', 'category', 'phone', 'email', 'website', 'address', 'city', 'latitude', 'longitude', 'hours', 'description', 'social'];

    private const MULTI = ['phone', 'email', 'social', 'category'];

    /** schema.org types that describe a page or a thing other than a company. */
    private const NOT_BUSINESS = [
        'website', 'webpage', 'webpageelement', 'breadcrumblist', 'listitem', 'itemlist', 'searchaction', 'person', 'imageobject',
        'videoobject', 'article', 'newsarticle', 'blogposting', 'product', 'offer', 'aggregateoffer', 'review', 'aggregaterating',
        'rating', 'faqpage', 'question', 'answer', 'event', 'sitenavigationelement', 'collectionpage', 'searchresultspage',
        'readaction', 'entrypoint', 'contactpoint', 'postaladdress', 'geocoordinates', 'openinghoursspecification', 'thing',
    ];

    /** Types too generic to be used as a category hint. */
    private const GENERIC_TYPES = ['localbusiness', 'organization', 'corporation', 'place', 'professionalservice', 'store', 'onlinebusiness'];

    /**
     * @param  array<string, string>  $selectors  field => selector
     * @return array{raw: array<string, string>|null, method: string, record: array<string, mixed>|null}
     */
    public static function extract(string $html, string $url, array $selectors = []): array
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($dom);

        $canonical = self::canonical($xpath, $url);
        $fromSelectors = self::fromSelectors($xpath, $selectors);
        $fromJsonLd = self::fromJsonLd($xpath, $canonical);

        $method = match (true) {
            $fromSelectors !== [] && $fromJsonLd !== [] => 'selectors+json-ld',
            $fromSelectors !== [] => 'selectors',
            $fromJsonLd !== [] => 'json-ld',
            default => 'none',
        };
        if ($method === 'none') {
            return ['raw' => null, 'method' => $method, 'record' => null];
        }

        $raw = array_replace($fromJsonLd, $fromSelectors);
        if (empty($raw['name']) && empty($raw['name_ar'])) {
            $raw['name'] = self::metaName($xpath);
        }
        if (empty($raw['description'])) {
            $raw['description'] = self::meta($xpath, 'og:description') ?: self::meta($xpath, 'description');
        }

        // A website on the directory's own domain is the listing page, not the company's site.
        if (! empty($raw['website']) && UrlGuard::sameSite(Normalizer::website($raw['website']) ?? '', $url)) {
            unset($raw['website']);
        }

        $raw['source_url'] = $canonical;
        $raw['external_id'] = $canonical;
        $raw = array_map(fn ($v) => is_string($v) ? trim($v) : $v, array_filter($raw, fn ($v) => $v !== null && $v !== ''));

        return ['raw' => $raw, 'method' => $method, 'record' => RecordBuilder::build($raw)];
    }

    /** @return array<string, string> */
    private static function fromSelectors(DOMXPath $xpath, array $selectors): array
    {
        $out = [];
        foreach ($selectors as $field => $selector) {
            if (! in_array($field, self::FIELDS, true) || trim((string) $selector) === '') {
                continue;
            }
            $values = Selector::values($xpath, (string) $selector, in_array($field, self::MULTI, true) ? 10 : 1);
            $values = array_map(fn ($v) => self::cleanValue($field, $v), $values);
            $values = array_values(array_filter($values, fn ($v) => $v !== ''));
            if ($values !== []) {
                $out[$field] = implode("\n", array_unique($values));
            }
        }

        return $out;
    }

    private static function cleanValue(string $field, string $value): string
    {
        return match ($field) {
            'phone' => trim((string) preg_replace('/^tel:/i', '', rawurldecode($value))),
            'email' => trim((string) preg_replace(['/^mailto:/i', '/\?.*$/'], '', rawurldecode($value))),
            default => $value,
        };
    }

    /** @return array<string, string> */
    private static function fromJsonLd(DOMXPath $xpath, string $pageUrl): array
    {
        $nodes = [];
        $breadcrumb = null;
        foreach ($xpath->query('//script[@type="application/ld+json"]') ?: [] as $script) {
            $json = json_decode(trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', ' ', $script->textContent)), true);
            if (! is_array($json)) {
                continue;
            }
            foreach (self::flatten($json) as $node) {
                $types = self::types($node);
                if (in_array('breadcrumblist', $types, true)) {
                    $breadcrumb = $node;
                } elseif ($types !== [] && array_diff($types, self::NOT_BUSINESS) !== [] && ! empty($node['name'])) {
                    $nodes[] = $node;
                }
            }
        }

        // The best-described company node, ignoring the directory's own Organization (its homepage URL).
        $best = null;
        $bestScore = 0;
        foreach ($nodes as $node) {
            $url = is_string($node['url'] ?? null) ? $node['url'] : null;
            if ($url !== null && UrlGuard::sameSite($url, $pageUrl) && in_array(rtrim((string) parse_url($url, PHP_URL_PATH), '/'), ['', '/ar', '/en'], true)) {
                continue;
            }
            $score = count(array_intersect(array_keys($node), ['address', 'telephone', 'email', 'geo', 'openingHours', 'openingHoursSpecification', 'sameAs', 'description']));
            $score += array_diff(self::types($node), ['organization', 'corporation']) !== [] ? 2 : 0;
            if ($score > $bestScore) {
                [$best, $bestScore] = [$node, $score];
            }
        }
        if ($best === null || $bestScore < 2) {
            return [];
        }

        $out = [];
        foreach ((array) $best['name'] as $name) {
            if (is_string($name)) {
                $out[Normalizer::hasArabic($name) && ! isset($out['name_ar']) ? 'name_ar' : 'name'] ??= $name;
            }
        }
        foreach ((array) ($best['alternateName'] ?? []) as $alt) {
            if (is_string($alt)) {
                $out[Normalizer::hasArabic($alt) ? 'name_ar' : 'name'] ??= $alt;
            }
        }

        $out['phone'] = implode("\n", self::strings($best['telephone'] ?? null));
        $out['email'] = implode("\n", array_map(fn ($e) => (string) preg_replace('/^mailto:/i', '', $e), self::strings($best['email'] ?? null)));

        $sameAs = self::strings($best['sameAs'] ?? null);
        $social = array_filter($sameAs, fn ($u) => RecordBuilder::platformFor($u) !== null);
        $out['social'] = implode("\n", $social);
        $websites = array_values(array_filter([...self::strings($best['url'] ?? null), ...array_diff($sameAs, $social)], fn ($u) => ! UrlGuard::sameSite($u, $pageUrl)));
        $out['website'] = $websites[0] ?? '';

        $address = $best['address'] ?? ($best['location']['address'] ?? null);
        if (is_string($address)) {
            $out['address'] = $address;
        } elseif (is_array($address)) {
            $address = isset($address[0]) && is_array($address[0]) ? $address[0] : $address;
            $out['address'] = implode(', ', array_filter(self::strings($address['streetAddress'] ?? null)));
            $out['city'] = self::strings($address['addressLocality'] ?? null)[0] ?? (self::strings($address['addressRegion'] ?? null)[0] ?? '');
            $out['po_box'] = self::strings($address['postOfficeBoxNumber'] ?? null)[0] ?? '';
        }

        $geo = $best['geo'] ?? null;
        if (is_array($geo) && isset($geo['latitude'], $geo['longitude'])) {
            $out['latitude'] = (string) $geo['latitude'];
            $out['longitude'] = (string) $geo['longitude'];
        }

        $out['hours'] = self::hours($best);
        $out['description'] = self::strings($best['description'] ?? null)[0] ?? '';
        $out['tags'] = implode("\n", self::strings($best['keywords'] ?? null));

        $category = self::breadcrumbCategory($breadcrumb);
        if ($category === null) {
            $specific = array_diff(self::types($best), self::GENERIC_TYPES);
            $first = reset($specific);
            $category = $first ? Str::headline($first) : null;
        }
        $out['category'] = (string) $category;

        return array_filter($out, fn ($v) => $v !== '');
    }

    /** @return list<array<string, mixed>> every object in a JSON-LD document, @graph included */
    private static function flatten(array $json, int $depth = 0): array
    {
        if ($depth > 4) {
            return [];
        }
        if ($json === []) {
            return [];
        }
        if (array_is_list($json)) {
            return array_merge(...array_map(fn ($item) => is_array($item) ? self::flatten($item, $depth + 1) : [], $json));
        }
        $nodes = isset($json['@type']) ? [$json] : [];
        if (isset($json['@graph']) && is_array($json['@graph'])) {
            $nodes = [...$nodes, ...self::flatten($json['@graph'], $depth + 1)];
        }
        // Pages often wrap the company in mainEntity / about / itemReviewed.
        foreach (['mainEntity', 'about', 'mainEntityOfPage'] as $key) {
            if (isset($json[$key]) && is_array($json[$key])) {
                $nodes = [...$nodes, ...self::flatten($json[$key], $depth + 1)];
            }
        }

        return $nodes;
    }

    /** @return list<string> lower-case schema.org type names */
    private static function types(array $node): array
    {
        return array_values(array_map(
            fn ($t) => Str::lower((string) preg_replace('#^https?://schema\.org/#i', '', (string) $t)),
            array_filter((array) ($node['@type'] ?? []), 'is_string'),
        ));
    }

    /** @return list<string> */
    private static function strings(mixed $value): array
    {
        $out = [];
        foreach (is_array($value) && ! isset($value['@type']) ? $value : [$value] as $item) {
            if (is_string($item) || is_numeric($item)) {
                $item = trim((string) $item);
                if ($item !== '') {
                    $out[] = $item;
                }
            } elseif (is_array($item) && isset($item['@id']) && is_string($item['@id'])) {
                $out[] = $item['@id'];
            }
        }

        return $out;
    }

    private static function hours(array $node): string
    {
        $lines = self::strings($node['openingHours'] ?? null);
        foreach ((array) ($node['openingHoursSpecification'] ?? []) as $spec) {
            if (! is_array($spec)) {
                continue;
            }
            $spec = isset($spec['@type']) || ! array_is_list($spec) ? $spec : ($spec[0] ?? []);
            $days = array_map(fn ($d) => (string) preg_replace('#^https?://schema\.org/#i', '', $d), self::strings($spec['dayOfWeek'] ?? null));
            $opens = substr((string) ($spec['opens'] ?? ''), 0, 5);
            $closes = substr((string) ($spec['closes'] ?? ''), 0, 5);
            if ($days !== [] && $opens !== '' && $closes !== '') {
                $lines[] = implode(', ', $days).' '.$opens.'–'.$closes;
            }
        }

        return implode('; ', array_unique($lines));
    }

    private static function breadcrumbCategory(?array $breadcrumb): ?string
    {
        $items = array_values(array_filter((array) ($breadcrumb['itemListElement'] ?? []), 'is_array'));
        usort($items, fn ($a, $b) => (int) ($a['position'] ?? 0) <=> (int) ($b['position'] ?? 0));
        if (count($items) < 3) {
            return null;
        }
        $parent = $items[count($items) - 2];
        $name = $parent['name'] ?? ($parent['item']['name'] ?? null);

        return is_string($name) && mb_strlen($name) <= 80 && ! preg_match('/^(home|الرئيسية)$/iu', trim($name)) ? trim($name) : null;
    }

    private static function canonical(DOMXPath $xpath, string $url): string
    {
        $href = $xpath->query('//link[@rel="canonical"]/@href')?->item(0)?->nodeValue;
        $canonical = $href ? Urls::resolve($url, $href) : null;

        return $canonical !== null && UrlGuard::sameSite($canonical, $url) ? $canonical : (Urls::normalize($url) ?? $url);
    }

    private static function meta(DOMXPath $xpath, string $name): string
    {
        $node = $xpath->query('//meta[@property="'.$name.'" or @name="'.$name.'"]/@content')?->item(0);

        return trim((string) $node?->nodeValue);
    }

    private static function metaName(DOMXPath $xpath): string
    {
        $h1 = $xpath->query('//h1')?->item(0);
        if ($h1 !== null && ($text = trim((string) preg_replace('/\s+/u', ' ', $h1->textContent))) !== '') {
            return $text;
        }

        return self::meta($xpath, 'og:title') ?: trim((string) $xpath->query('//title')?->item(0)?->textContent);
    }
}
