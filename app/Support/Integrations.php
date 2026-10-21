<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Third-party integrations configured from Admin → Settings → Google & integrations.
 * Scripts only load in production (or when explicitly enabled) and only once an ID is set.
 */
final class Integrations
{
    /** Regions where analytics and ad storage stay denied until the visitor consents (EEA, UK, Switzerland). */
    public const CONSENT_REGIONS = ['AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IS', 'IE', 'IT', 'LV', 'LI', 'LT', 'LU', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'GB', 'CH'];

    public static function enabled(): bool
    {
        return (bool) config('services.integrations.enabled', app()->isProduction());
    }

    public static function ga4(): ?string
    {
        return self::enabled() ? self::valid('integrations.ga4_id', '/^G-[A-Z0-9]{4,15}$/') : null;
    }

    /** Publisher ID ("ca-pub-…") — also used for ads.txt and the AdSense site-verification meta tag. */
    public static function adsenseClient(): ?string
    {
        return self::valid('integrations.adsense_client', '/^ca-pub-\d{10,20}$/');
    }

    /** Ads load only on public, indexable content pages (AdSense policy: no ads on thin or non-content screens). */
    public static function showAds(): bool
    {
        return self::enabled() && self::adsenseClient() !== null && setting('integrations.adsense_enabled', true) && seo()->shouldIndex();
    }

    public static function consentBanner(): bool
    {
        return (self::ga4() !== null || self::showAds()) && (bool) setting('integrations.consent_banner', true);
    }

    /** @return array<string,string> meta name => content */
    public static function verificationMeta(): array
    {
        return array_filter([
            'google-site-verification' => self::valid('integrations.google_verification', '/^[A-Za-z0-9_\-]{10,100}$/'),
            'msvalidate.01' => self::valid('integrations.bing_verification', '/^[A-Za-z0-9]{10,64}$/'),
            'yandex-verification' => self::valid('integrations.yandex_verification', '/^[A-Za-z0-9]{10,64}$/'),
            'google-adsense-account' => self::adsenseClient(),
        ]);
    }

    /** Stable IndexNow key derived from the application key (served at /indexnow.txt). */
    public static function indexNowKey(): string
    {
        return substr(hash_hmac('sha256', 'indexnow', (string) config('app.key')), 0, 32);
    }

    private static function valid(string $key, string $pattern): ?string
    {
        $value = trim((string) setting($key));

        return $value !== '' && preg_match($pattern, $value) ? $value : null;
    }
}
