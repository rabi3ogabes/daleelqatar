<?php

declare(strict_types=1);

namespace App\Support\Import;

use App\Enums\SocialPlatform;
use Illuminate\Support\Str;

/**
 * Builds a clean, normalised company record from one spreadsheet row (or one
 * crawled page). Ratings, review counts, "verified/premium" flags and personal
 * contact names from third-party sources are deliberately ignored: this
 * directory only shows ratings and verification it earned itself.
 */
final class RecordBuilder
{
    /** Qatar bounding box: pins outside it are discarded. */
    private const QATAR = ['lat' => [24.4, 26.25], 'lng' => [50.65, 51.75]];

    /**
     * @param  list<string>  $cells
     * @param  array<string,int>  $mapping
     * @return array<string, mixed>|null null for rows without a company name
     */
    public static function fromRow(array $cells, array $mapping): ?array
    {
        $get = fn (string $field) => isset($mapping[$field]) ? trim((string) ($cells[$mapping[$field]] ?? '')) : '';

        return self::build([
            'name' => $get('name'),
            'name_ar' => $get('name_ar'),
            'category' => $get('category'),
            'tags' => $get('tags'),
            'phone' => $get('phone'),
            'mobile' => $get('mobile'),
            'landline' => $get('landline'),
            'other_phones' => $get('other_phones'),
            'email' => $get('email'),
            'other_emails' => $get('other_emails'),
            'website' => $get('website'),
            'social' => $get('social'),
            'address' => $get('address'),
            'city' => $get('city'),
            'po_box' => $get('po_box'),
            'latitude' => $get('latitude'),
            'longitude' => $get('longitude'),
            'map_precision' => isset($mapping['map_precision']) ? $get('map_precision') : null,
            'hours' => $get('hours'),
            'description' => $get('description'),
            'source_url' => $get('source_url'),
            'external_id' => $get('external_id'),
        ]);
    }

    /**
     * @param  array<string, string|null>  $raw  values keyed like ColumnMapper::FIELDS
     * @return array<string, mixed>|null
     */
    public static function build(array $raw): ?array
    {
        [$nameEn, $nameAr] = self::names((string) ($raw['name'] ?? ''), (string) ($raw['name_ar'] ?? ''));
        if ($nameEn === null && $nameAr === null) {
            return null;
        }

        // Phones: main number, a WhatsApp-capable mobile and the rest as extras.
        $phoneCol = Normalizer::phones($raw['phone'] ?? '');
        $mobileCol = Normalizer::phones($raw['mobile'] ?? '');
        $all = array_values(array_unique([...$phoneCol, ...$mobileCol, ...Normalizer::phones($raw['landline'] ?? ''), ...Normalizer::phones($raw['other_phones'] ?? '')]));
        $primary = $phoneCol[0] ?? $all[0] ?? null;
        $whatsapp = null;
        foreach ([...$mobileCol, ...$all] as $p) {
            if (Normalizer::isQatarMobile($p)) {
                $whatsapp = $p;
                break;
            }
        }
        $extraPhones = array_values(array_diff($all, array_filter([$primary, $whatsapp])));

        // E-mails.
        $emails = array_values(array_unique([...Normalizer::emails($raw['email'] ?? ''), ...Normalizer::emails($raw['other_emails'] ?? '')]));

        // Website vs. social profiles (directories often put an Instagram link in "Website").
        $social = self::socialLinks((string) ($raw['social'] ?? ''));
        $website = null;
        foreach (preg_split('/[\s,;]+/', (string) ($raw['website'] ?? '')) ?: [] as $candidate) {
            $url = Normalizer::website($candidate);
            if ($url === null) {
                continue;
            }
            if (($platform = self::platformFor($url)) !== null) {
                $social[$platform] ??= $url;
            } elseif ($website === null && Normalizer::domain($url) !== null) {
                $website = $url;
            }
        }

        // Location.
        [$lat, $lng, $precision] = self::coordinates($raw['latitude'] ?? '', $raw['longitude'] ?? '', $raw['map_precision'] ?? null);
        $address = Normalizer::text($raw['address'] ?? '', 300);
        if ($address !== null) {
            $address = trim(preg_replace('/\s+,/', ',', $address) ?? $address, ' ,');
        }
        $poBox = Normalizer::text($raw['po_box'] ?? '', 20);
        if ($poBox && ! str_contains(Str::lower((string) $address), Str::lower($poBox))) {
            $address = trim(($address ? $address.', ' : '').'P.O. Box '.$poBox);
        }
        $address = $address !== null ? Str::limit($address, 300, '') : null;

        $categories = self::splitList((string) ($raw['category'] ?? ''), '/\s*(?:·|;|\||\n)\s*/u');
        $tags = self::splitList((string) ($raw['tags'] ?? ''), '/\s*(?:·|;|,|\||\n)\s*/u', 20);

        return [
            'name_en' => $nameEn,
            'name_ar' => $nameAr,
            'categories' => $categories,
            'tags' => $tags,
            'phone' => $primary,
            'whatsapp' => $whatsapp,
            'extra_phones' => array_slice($extraPhones, 0, 8),
            'email' => $emails[0] ?? null,
            'extra_emails' => array_slice($emails, 1, 8),
            'website' => $website,
            'social' => $social,
            'address' => $address,
            'city' => Normalizer::text($raw['city'] ?? '', 120),
            'latitude' => $lat,
            'longitude' => $lng,
            'precision' => $precision,
            'hours' => Normalizer::text($raw['hours'] ?? '', 1000),
            'description' => self::description((string) ($raw['description'] ?? '')),
            'external_id' => Normalizer::text($raw['external_id'] ?? '', 191),
            'source_url' => Normalizer::website($raw['source_url'] ?? '') ? Str::limit((string) $raw['source_url'], 500, '') : null,
        ];
    }

    /** @return array{0: ?string, 1: ?string} [English name, Arabic name] */
    private static function names(string $name, string $nameAr): array
    {
        $name = Normalizer::text($name, 160);
        $nameAr = Normalizer::text($nameAr, 160);
        if ($name !== null && $nameAr === null && Normalizer::hasArabic($name)) {
            // "Al Meera | الميرة" → split by script; a purely Arabic name stays Arabic.
            $parts = preg_split('/\s*[|\/–—-]\s*/u', $name) ?: [$name];
            $latin = collect($parts)->first(fn ($p) => ! Normalizer::hasArabic($p) && preg_match('/\p{L}/u', $p));
            $arabic = collect($parts)->first(fn ($p) => Normalizer::hasArabic($p));
            if ($latin && $arabic) {
                return [trim($latin), trim($arabic)];
            }

            return [null, $name];
        }
        if ($name !== null) {
            // Directory SEO suffixes such as "– Leading Digital Agency in Qatar" are not part of the name.
            $name = trim(preg_replace('/\s+[–—|]\s+.*$/u', '', $name) ?? $name);
            $name = Str::limit(trim($name, " \t-–—|"), 160, '');
        }

        return [$name ?: null, $nameAr];
    }

    /** @return array{0: ?float, 1: ?float, 2: ?string} */
    private static function coordinates(?string $lat, ?string $lng, ?string $precisionText): array
    {
        $lat = is_numeric(trim((string) $lat)) ? (float) $lat : null;
        $lng = is_numeric(trim((string) $lng)) ? (float) $lng : null;
        if ($lat === null || $lng === null
            || $lat < self::QATAR['lat'][0] || $lat > self::QATAR['lat'][1]
            || $lng < self::QATAR['lng'][0] || $lng > self::QATAR['lng'][1]) {
            return [null, null, null];
        }

        if ($precisionText === null) {
            return [round($lat, 7), round($lng, 7), 'exact'];
        }
        $p = Str::lower($precisionText);
        if (str_contains($p, 'exact') || str_contains($p, 'pinned') || str_contains($p, 'rooftop')) {
            return [round($lat, 7), round($lng, 7), 'exact'];
        }

        // City-centre or address-level guesses would put many companies on one fake pin.
        return [null, null, null];
    }

    /** @return array<string, string> platform => url */
    private static function socialLinks(string $text): array
    {
        $out = [];
        preg_match_all('#https?://[^\s,;"\'<>]+#i', $text, $m);
        foreach ($m[0] as $url) {
            $url = rtrim($url, ').');
            if (($platform = self::platformFor($url)) !== null && filter_var($url, FILTER_VALIDATE_URL)) {
                $out[$platform] ??= Str::limit($url, 300, '');
            }
        }

        return $out;
    }

    public static function platformFor(string $url): ?string
    {
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^(www|m|web|mobile)\./', '', $host) ?? $host;

        $platform = match (true) {
            str_ends_with($host, 'instagram.com') => SocialPlatform::Instagram,
            str_ends_with($host, 'facebook.com'), $host === 'fb.com', $host === 'fb.me' => SocialPlatform::Facebook,
            $host === 'twitter.com', $host === 'x.com' => SocialPlatform::X,
            str_ends_with($host, 'linkedin.com') => SocialPlatform::LinkedIn,
            str_ends_with($host, 'youtube.com'), $host === 'youtu.be' => SocialPlatform::YouTube,
            str_ends_with($host, 'tiktok.com') => SocialPlatform::TikTok,
            str_ends_with($host, 'snapchat.com') => SocialPlatform::Snapchat,
            default => null,
        };

        return $platform?->value;
    }

    /** Removes "listed on <other directory>" boilerplate; keeps the factual company description. */
    private static function description(string $text): ?string
    {
        $text = (string) Normalizer::text($text, 5000);
        if ($text === '') {
            return null;
        }
        $sources = 'QBCD|Connect Qatar|Qatar Living|QatarLiving|QatarYello|Qatar Yello|Qatar Contact|QatarContact|Business Directory Middle East|businessdirectorymiddleeast(?:\.com)?';
        $text = preg_replace('/[^.!?\n]*\bis listed on (?:'.$sources.')\b[^.!?\n]*(?:\n[^.!?\n]+)?[.!?]?/iu', '', $text) ?? $text;
        $text = preg_replace('/\s+(?:on|at|via|from)\s+(?:'.$sources.')\b/iu', '', $text) ?? $text;
        $text = trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);

        return mb_strlen($text) >= 20 ? $text : null;
    }

    /** @return list<string> */
    private static function splitList(string $text, string $pattern, int $limit = 10): array
    {
        $items = [];
        foreach (preg_split($pattern, $text) ?: [] as $item) {
            $item = trim($item, " \t\n.");
            if ($item !== '' && mb_strlen($item) <= 120) {
                $items[Str::lower($item)] = $item;
            }
        }

        return array_slice(array_values($items), 0, $limit);
    }
}
