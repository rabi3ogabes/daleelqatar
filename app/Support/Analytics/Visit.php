<?php

declare(strict_types=1);

namespace App\Support\Analytics;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * What a page view says about the visit — device, browser, system, country and
 * where the visitor came from — worked out from the request alone. Nothing is
 * kept that identifies a person: no IP address and no user-agent string.
 */
final class Visit
{
    private const BOTS = '/bot|crawl|spider|slurp|preview|facebookexternalhit|headless|lighthouse|pagespeed|gtmetrix|pingdom|uptime|monitor|curl|wget|python|httpclient|java\/|go-http|axios|node-fetch|phantom|selenium|puppeteer|playwright/i';

    private const SEARCH = ['google' => 'Google', 'bing' => 'Bing', 'yahoo' => 'Yahoo', 'duckduckgo' => 'DuckDuckGo', 'yandex' => 'Yandex', 'baidu' => 'Baidu', 'ecosia' => 'Ecosia', 'brave' => 'Brave', 'naver' => 'Naver'];

    private const SOCIAL = ['facebook' => 'Facebook', 'fb.' => 'Facebook', 'instagram' => 'Instagram', 't.co' => 'X', 'twitter' => 'X', 'x.com' => 'X', 'linkedin' => 'LinkedIn', 'lnkd.in' => 'LinkedIn',
        'tiktok' => 'TikTok', 'snapchat' => 'Snapchat', 'whatsapp' => 'WhatsApp', 'wa.me' => 'WhatsApp', 'youtube' => 'YouTube', 'pinterest' => 'Pinterest', 'reddit' => 'Reddit', 'telegram' => 'Telegram', 't.me' => 'Telegram'];

    public static function isBot(?string $userAgent): bool
    {
        return $userAgent === null || trim($userAgent) === '' || preg_match(self::BOTS, $userAgent) === 1;
    }

    /** @return array{device: string, browser: ?string, os: ?string} */
    public static function agent(?string $ua): array
    {
        $ua = (string) $ua;
        $device = match (true) {
            (bool) preg_match('/ipad|tablet|kindle|silk|playbook|(android(?!.*mobile))/i', $ua) => 'tablet',
            (bool) preg_match('/mobi|iphone|ipod|android|blackberry|opera mini|iemobile/i', $ua) => 'mobile',
            default => 'desktop',
        };
        $browser = match (true) {
            (bool) preg_match('/edg(e|a|ios)?\//i', $ua) => 'Edge',
            (bool) preg_match('/opr\/|opera/i', $ua) => 'Opera',
            (bool) preg_match('/samsungbrowser/i', $ua) => 'Samsung Internet',
            (bool) preg_match('/firefox|fxios/i', $ua) => 'Firefox',
            (bool) preg_match('/crios|chrome|chromium/i', $ua) => 'Chrome',
            (bool) preg_match('/safari/i', $ua) => 'Safari',
            default => null,
        };
        $os = match (true) {
            (bool) preg_match('/iphone|ipad|ipod|ios/i', $ua) => 'iOS',
            (bool) preg_match('/android/i', $ua) => 'Android',
            (bool) preg_match('/windows/i', $ua) => 'Windows',
            (bool) preg_match('/cros/i', $ua) => 'ChromeOS',
            (bool) preg_match('/mac os x|macintosh/i', $ua) => 'macOS',
            (bool) preg_match('/linux/i', $ua) => 'Linux',
            default => null,
        };

        return ['device' => $device, 'browser' => $browser, 'os' => $os];
    }

    /** Country (ISO code) and city from the CDN's geolocation headers. @return array{country: ?string, city: ?string} */
    public static function place(Request $request): array
    {
        $country = strtoupper(trim((string) ($request->header('x-vercel-ip-country') ?: $request->header('cf-ipcountry'))));
        $city = trim(rawurldecode((string) $request->header('x-vercel-ip-city')));

        return [
            'country' => preg_match('/^[A-Z]{2}$/', $country) && ! in_array($country, ['XX', 'T1'], true) ? $country : null,
            'city' => $city !== '' ? Str::limit(strip_tags($city), 80, '') : null,
        ];
    }

    /**
     * Where the visit came from: direct, search, social, referral or a campaign.
     *
     * @return array{channel: string, referrer: ?string}
     */
    public static function source(?string $referrer, ?string $utm, string $ownHost): array
    {
        $host = Str::lower((string) parse_url((string) $referrer, PHP_URL_HOST));
        $host = (string) preg_replace('/^(www|m|l|lm|mobile)\./', '', $host);
        $own = (string) preg_replace('/^www\./', '', Str::lower($ownHost));

        if ($utm !== null && $utm !== '') {
            return ['channel' => 'campaign', 'referrer' => $host ?: null];
        }
        if ($host === '' || $host === $own || str_ends_with($host, '.'.$own)) {
            return ['channel' => $host === '' ? 'direct' : 'internal', 'referrer' => null];
        }
        foreach (self::SEARCH as $needle => $name) {
            if (preg_match('/(^|\.)'.preg_quote($needle, '/').'\./', $host.'.')) {
                return ['channel' => 'search', 'referrer' => $name];
            }
        }
        foreach (self::SOCIAL as $needle => $name) {
            if (str_contains($host, $needle)) {
                return ['channel' => 'social', 'referrer' => $name];
            }
        }

        return ['channel' => 'referral', 'referrer' => Str::limit($host, 120, '')];
    }

    /** Country name in the reader's language ("QA" → "Qatar" / "قطر"). */
    public static function countryName(?string $code, ?string $locale = null): string
    {
        if ($code === null) {
            return __('analytics.unknown');
        }
        $name = class_exists(\Locale::class) ? \Locale::getDisplayRegion('-'.$code, $locale ?? app()->getLocale()) : '';

        return $name !== '' && $name !== $code ? $name : $code;
    }

    /** "QA" → 🇶🇦 */
    public static function flag(?string $code): string
    {
        if ($code === null || ! preg_match('/^[A-Z]{2}$/', $code)) {
            return '🌐';
        }

        return mb_chr(0x1F1E6 + ord($code[0]) - 65).mb_chr(0x1F1E6 + ord($code[1]) - 65);
    }
}
