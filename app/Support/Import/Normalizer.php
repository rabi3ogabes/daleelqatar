<?php

declare(strict_types=1);

namespace App\Support\Import;

use App\Support\Search\ArabicNormalizer;
use Illuminate\Support\Str;

/**
 * Turns messy directory data into comparable values: Qatar phone numbers,
 * e-mail addresses, website domains and company-name keys.
 */
final class Normalizer
{
    /** Hosts shared by many businesses — never a reliable identity on their own. */
    private const SHARED_HOSTS = [
        'facebook.com', 'fb.com', 'instagram.com', 'twitter.com', 'x.com', 'linkedin.com', 'youtube.com', 'youtu.be',
        'tiktok.com', 'snapchat.com', 'wa.me', 'whatsapp.com', 'linktr.ee', 'share.google', 'google.com', 'goo.gl',
        'maps.app.goo.gl', 'g.page', 'bit.ly', 'blogspot.com', 'wordpress.com', 'wixsite.com', 'business.site',
        'qatarliving.com', 'qataryello.com', 'qatarcontact.com', 'connect.com.qa', 'businessdirectorymiddleeast.com',
        'yellowpages.qa', 'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com',
        // Free and ISP mail providers: an address there says nothing about the company's website.
        'googlemail.com', 'live.com', 'msn.com', 'icloud.com', 'me.com', 'mac.com', 'aol.com', 'ymail.com', 'rocketmail.com',
        'proton.me', 'protonmail.com', 'gmx.com', 'gmx.net', 'mail.com', 'yandex.com', 'yandex.ru', 'zoho.com', 'zohomail.com',
        'rediffmail.com', 'qatar.net.qa', 'live.co.uk', 'hotmail.co.uk', 'yahoo.co.uk', 'yahoo.co.in', 'hotmail.fr', 'live.fr',
        'outlook.sa', 'hotmail.it', 'web.de', 'mail.ru', 'inbox.com', 'hushmail.com', '163.com', 'qq.com',
    ];

    /** Legal-form words ignored when comparing company names. */
    private const NAME_NOISE = [
        'wll', 'w l l', 'llc', 'l l c', 'spc', 'qpsc', 'qsc', 'q s c', 'qfzc', 'qstp', 'co', 'company', 'est', 'establishment',
        'the', 'and', 'ltd', 'limited', 'inc', 'group', 'qatar branch', 'branch',
    ];

    /**
     * Canonical Qatar number "+974XXXXXXXX" (or "+<digits>" for foreign numbers), null when invalid.
     */
    public static function phone(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', self::latinDigits((string) $raw)) ?? '';
        if ($digits === '') {
            return null;
        }
        $digits = preg_replace('/^00/', '', $digits) ?? $digits;

        if (strlen($digits) === 11 && str_starts_with($digits, '974')) {
            return '+'.$digits;
        }
        if (strlen($digits) === 8 && preg_match('/^[2-7]/', $digits)) {
            return '+974'.$digits;
        }
        if (strlen($digits) >= 9 && strlen($digits) <= 15 && ! str_starts_with($digits, '974')) {
            return '+'.$digits;
        }

        return null;
    }

    /** "+974 4436 6666" — the format shown to visitors. */
    public static function displayPhone(string $canonical): string
    {
        if (preg_match('/^\+974(\d{4})(\d{4})$/', $canonical, $m)) {
            return '+974 '.$m[1].' '.$m[2];
        }

        return $canonical;
    }

    /** Qatar mobile numbers start with 3, 5, 6 or 7 — those can receive WhatsApp. */
    public static function isQatarMobile(string $canonical): bool
    {
        return (bool) preg_match('/^\+974[3567]\d{7}$/', $canonical);
    }

    /** @return list<string> canonical numbers found in a free-text cell */
    public static function phones(?string $raw): array
    {
        $out = [];
        foreach (preg_split('/[\n,;\/|]+|\s{2,}/u', self::latinDigits((string) $raw)) ?: [] as $part) {
            if (($p = self::phone($part)) !== null) {
                $out[$p] = true;
            }
        }

        return array_keys($out);
    }

    public static function email(?string $raw): ?string
    {
        $email = Str::lower(trim((string) $raw, " \t\n\r\0\x0B<>()[]\"'.,;"));
        $email = preg_replace('/^mailto:/', '', $email) ?? $email;

        return filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 191 ? $email : null;
    }

    /** @return list<string> */
    public static function emails(?string $raw): array
    {
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', (string) $raw, $m);
        $out = [];
        foreach ($m[0] as $candidate) {
            if (($e = self::email($candidate)) !== null) {
                $out[$e] = true;
            }
        }

        return array_keys($out);
    }

    /** Normalised https URL, or null when the value is not a usable website. */
    public static function website(?string $raw): ?string
    {
        $url = trim((string) $raw);
        if ($url === '' || str_contains($url, '@') || str_contains($url, ' ')) {
            return null;
        }
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || ! str_contains($host, '.') || strlen($url) > 300) {
            return null;
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    /** Registrable host without "www." — null for shared platforms (social networks, directories, free mail). */
    public static function domain(?string $url): ?string
    {
        $host = parse_url((string) $url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }
        $host = Str::lower(preg_replace('/^www\d?\./i', '', $host) ?? $host);

        foreach (self::SHARED_HOSTS as $shared) {
            if ($host === $shared || str_ends_with($host, '.'.$shared)) {
                return null;
            }
        }

        return strlen($host) <= 191 ? $host : null;
    }

    public static function emailDomain(string $email): ?string
    {
        return self::domain('https://'.Str::after($email, '@'));
    }

    /** Comparable company name: lower case, no punctuation, no legal-form words. */
    public static function nameKey(?string $name): ?string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }
        $key = Str::lower(Str::ascii(ArabicNormalizer::normalize($name)));
        if ($key === '') {
            $key = ArabicNormalizer::normalize($name);
        }
        $key = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $key) ?? $key;
        $key = ' '.trim(preg_replace('/\s+/', ' ', $key) ?? $key).' ';
        foreach (self::NAME_NOISE as $noise) {
            $key = str_replace(' '.$noise.' ', ' ', $key);
        }
        $key = trim(preg_replace('/\s+/', ' ', $key) ?? $key);

        return $key === '' ? null : Str::limit($key, 200, '');
    }

    /** Similarity of two company names between 0 and 1 (token overlap, tolerant to word order). */
    public static function nameSimilarity(?string $a, ?string $b): float
    {
        $ka = self::nameKey($a);
        $kb = self::nameKey($b);
        if ($ka === null || $kb === null) {
            return 0.0;
        }
        if ($ka === $kb) {
            return 1.0;
        }
        $ta = array_unique(explode(' ', $ka));
        $tb = array_unique(explode(' ', $kb));
        $common = count(array_intersect($ta, $tb));
        $tokens = $common / max(1, min(count($ta), count($tb)));
        similar_text(str_replace(' ', '', $ka), str_replace(' ', '', $kb), $percent);

        return max($tokens, $percent / 100);
    }

    /** Collapse whitespace and trim; null for empty or placeholder values. */
    public static function text(?string $raw, int $limit = 0): ?string
    {
        $text = trim(preg_replace('/[ \t]+/u', ' ', str_replace(["\r\n", "\r"], "\n", (string) $raw)) ?? '');
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        if ($text === '' || in_array(Str::lower($text), ['n/a', 'na', '-', '—', 'null', 'none', 'nil', '0', 'search ↗', 'open map ↗', 'send enquiry ↗'], true)) {
            return null;
        }

        return $limit > 0 ? Str::limit($text, $limit, '') : $text;
    }

    public static function latinDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }

    public static function hasArabic(string $value): bool
    {
        return (bool) preg_match('/\p{Arabic}/u', $value);
    }
}
