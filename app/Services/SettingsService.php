<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Administrator-editable settings with sensible defaults. Values are cached
 * and the cache is flushed whenever a setting changes.
 */
class SettingsService
{
    private const CACHE_KEY = 'site_settings.v1';

    private ?array $loaded = null;

    /** Defaults double as the schema of the settings screen. */
    public static function defaults(): array
    {
        return [
            // Brand
            'brand.name' => ['group' => 'brand', 'type' => 'text_i18n', 'value' => ['ar' => 'دليل قطر', 'en' => 'Daleel Qatar']],
            'brand.slogan' => ['group' => 'brand', 'type' => 'text_i18n', 'value' => ['ar' => 'اكتشف قطر، بكل ما فيها.', 'en' => 'Discover Qatar. Find Everything.']],
            'brand.subtitle' => ['group' => 'brand', 'type' => 'text_i18n', 'value' => ['ar' => 'دليلك الشامل للأعمال والخدمات في قطر.', 'en' => 'Your complete guide to businesses and services in Qatar.']],
            'brand.logo_path' => ['group' => 'brand', 'type' => 'image', 'value' => null],

            // Homepage
            'home.hero_title' => ['group' => 'home', 'type' => 'text_i18n', 'value' => ['ar' => 'اكتشف أفضل الأعمال والخدمات في قطر', 'en' => 'Discover the best businesses and services in Qatar']],
            'home.hero_subtitle' => ['group' => 'home', 'type' => 'text_i18n', 'value' => ['ar' => 'دليلك الشامل للأعمال والخدمات في قطر.', 'en' => 'Your complete guide to businesses and services in Qatar.']],
            'home.hero_image_path' => ['group' => 'home', 'type' => 'image', 'value' => null],
            'home.show_offers' => ['group' => 'home', 'type' => 'boolean', 'value' => true],
            'home.show_trust' => ['group' => 'home', 'type' => 'boolean', 'value' => true],

            // Contact & social
            'contact.email' => ['group' => 'contact', 'type' => 'email', 'value' => 'info@daleelqatar.com'],
            'contact.phone' => ['group' => 'contact', 'type' => 'text', 'value' => null],
            'contact.whatsapp' => ['group' => 'contact', 'type' => 'text', 'value' => null],
            'contact.address' => ['group' => 'contact', 'type' => 'text_i18n', 'value' => ['ar' => 'الدوحة، قطر', 'en' => 'Doha, Qatar']],
            'social.instagram' => ['group' => 'contact', 'type' => 'url', 'value' => null],
            'social.x' => ['group' => 'contact', 'type' => 'url', 'value' => null],
            'social.facebook' => ['group' => 'contact', 'type' => 'url', 'value' => null],
            'social.linkedin' => ['group' => 'contact', 'type' => 'url', 'value' => null],
            'social.tiktok' => ['group' => 'contact', 'type' => 'url', 'value' => null],
            'social.facebook_app_id' => ['group' => 'contact', 'type' => 'text', 'value' => null, 'pattern' => '/^\d{5,20}$/'],
            'footer.about' => ['group' => 'contact', 'type' => 'textarea_i18n', 'value' => [
                'ar' => 'دليل قطر منصة رقمية تساعدك على اكتشاف الأعمال والخدمات الموثوقة في جميع أنحاء قطر.',
                'en' => 'Daleel Qatar is a digital platform that helps you discover trusted businesses and services across Qatar.',
            ]],

            // Listings & moderation
            'listings.auto_approve' => ['group' => 'moderation', 'type' => 'boolean', 'value' => false],
            'listings.reapprove_on_edit' => ['group' => 'moderation', 'type' => 'boolean', 'value' => false],
            'listings.max_per_owner' => ['group' => 'moderation', 'type' => 'integer', 'value' => 10],
            'reviews.enabled' => ['group' => 'moderation', 'type' => 'boolean', 'value' => true],
            'reviews.require_moderation' => ['group' => 'moderation', 'type' => 'boolean', 'value' => true],
            'reviews.owner_replies' => ['group' => 'moderation', 'type' => 'boolean', 'value' => true],
            'reviews.min_length' => ['group' => 'moderation', 'type' => 'integer', 'value' => 20],
            'offers.require_moderation' => ['group' => 'moderation', 'type' => 'boolean', 'value' => true],

            // Uploads
            'uploads.max_image_kb' => ['group' => 'uploads', 'type' => 'integer', 'value' => 5120],
            'uploads.max_document_kb' => ['group' => 'uploads', 'type' => 'integer', 'value' => 8192],
            'uploads.max_gallery_images' => ['group' => 'uploads', 'type' => 'integer', 'value' => 12],

            // Notifications
            'notifications.admin_email' => ['group' => 'notifications', 'type' => 'email', 'value' => null],
            'notifications.notify_admins_new_listing' => ['group' => 'notifications', 'type' => 'boolean', 'value' => true],
            'notifications.notify_admins_contact' => ['group' => 'notifications', 'type' => 'boolean', 'value' => true],

            // SEO & analytics
            'seo.default_title' => ['group' => 'seo', 'type' => 'text_i18n', 'value' => ['ar' => 'دليل قطر — اكتشف قطر، بكل ما فيها', 'en' => 'Daleel Qatar — Discover Qatar. Find Everything.']],
            'seo.default_description' => ['group' => 'seo', 'type' => 'textarea_i18n', 'value' => [
                'ar' => 'دليل قطر هو دليلك الشامل للأعمال والخدمات في قطر: مطاعم، متاجر، شركات، خدمات وعروض في الدوحة ولوسيل والريان وجميع البلديات.',
                'en' => 'Daleel Qatar is your complete guide to businesses and services in Qatar — restaurants, shops, companies, services and offers across Doha, Lusail, Al Rayyan and every municipality.',
            ]],
            'seo.sitemap_enabled' => ['group' => 'seo', 'type' => 'boolean', 'value' => true],
            'seo.indexing_enabled' => ['group' => 'seo', 'type' => 'boolean', 'value' => true],
            'analytics.track_views' => ['group' => 'seo', 'type' => 'boolean', 'value' => true],
            'analytics.track_searches' => ['group' => 'seo', 'type' => 'boolean', 'value' => true],

            // Google & search-engine integrations (all optional; nothing loads until an ID is set)
            'integrations.google_verification' => ['group' => 'integrations', 'type' => 'text', 'value' => null, 'pattern' => '/^[A-Za-z0-9_\-]{10,100}$/', 'meta' => true],
            'integrations.bing_verification' => ['group' => 'integrations', 'type' => 'text', 'value' => null, 'pattern' => '/^[A-Za-z0-9]{10,64}$/', 'meta' => true],
            'integrations.yandex_verification' => ['group' => 'integrations', 'type' => 'text', 'value' => null, 'pattern' => '/^[A-Za-z0-9]{10,64}$/', 'meta' => true],
            'integrations.ga4_id' => ['group' => 'integrations', 'type' => 'text', 'value' => null, 'pattern' => '/^G-[A-Z0-9]{4,15}$/'],
            'integrations.adsense_client' => ['group' => 'integrations', 'type' => 'text', 'value' => null, 'pattern' => '/^ca-pub-\d{10,20}$/'],
            'integrations.adsense_enabled' => ['group' => 'integrations', 'type' => 'boolean', 'value' => true],
            'integrations.consent_banner' => ['group' => 'integrations', 'type' => 'boolean', 'value' => true],
            'integrations.indexnow' => ['group' => 'integrations', 'type' => 'boolean', 'value' => true],
        ];
    }

    public static function groups(): array
    {
        return ['brand', 'home', 'contact', 'moderation', 'uploads', 'notifications', 'seo', 'integrations'];
    }

    public function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        $stored = [];
        try {
            $stored = Cache::rememberForever(self::CACHE_KEY, function () {
                if (! Schema::hasTable('site_settings')) {
                    return [];
                }

                return SiteSetting::query()->pluck('value', 'key')->all();
            });
        } catch (Throwable) {
            // Database unavailable (e.g. during installation) — fall back to defaults.
        }

        $values = array_map(fn (array $d) => $d['value'], self::defaults());

        return $this->loaded = array_replace($values, $stored);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->all(), $key, $default) ?? $default;
    }

    public function bool(string $key): bool
    {
        return filter_var($this->get($key, false), FILTER_VALIDATE_BOOLEAN);
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) ($this->get($key, $default) ?? $default);
    }

    public function set(string $key, mixed $value, ?int $userId = null): void
    {
        $group = self::defaults()[$key]['group'] ?? 'custom';
        SiteSetting::query()->updateOrCreate(['key' => $key], ['group' => $group, 'value' => $value, 'updated_by' => $userId]);
        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->loaded = null;
    }
}
