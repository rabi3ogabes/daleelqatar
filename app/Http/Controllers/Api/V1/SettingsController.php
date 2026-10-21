<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** Public, non-secret configuration the mobile app needs at start-up. */
class SettingsController extends Controller
{
    public function app(): JsonResponse
    {
        $both = fn (string $key, string $fallback) => [
            'ar' => (setting($key) ?? [])['ar'] ?? __($fallback, [], 'ar'),
            'en' => (setting($key) ?? [])['en'] ?? __($fallback, [], 'en'),
        ];

        return response()->json(['data' => [
            'brand' => [
                'name' => $both('brand.name', 'brand.name'),
                'slogan' => $both('brand.slogan', 'brand.slogan'),
                'subtitle' => $both('brand.subtitle', 'brand.subtitle'),
                'logo_url' => asset('brand/daleel-qatar-logo-stacked-light.svg'),
                'icon_url' => asset('brand/icon-512.png'),
            ],
            'contact' => [
                'email' => setting('contact.email'),
                'phone' => setting('contact.phone'),
                'whatsapp' => setting('contact.whatsapp'),
            ],
            'social' => collect(['instagram', 'x', 'facebook', 'linkedin', 'tiktok'])
                ->mapWithKeys(fn ($k) => [$k => safe_external_url(setting('social.'.$k))])->filter()->all(),
            'locales' => ['supported' => ['ar', 'en'], 'default' => 'ar'],
            'timezone' => 'Asia/Qatar',
            'currency' => 'QAR',
            'reviews' => [
                'enabled' => (bool) setting('reviews.enabled', true),
                'require_moderation' => (bool) setting('reviews.require_moderation', true),
                'min_length' => (int) setting('reviews.min_length', 20),
                'owner_replies' => (bool) setting('reviews.owner_replies', true),
            ],
            'uploads' => [
                'max_image_kb' => (int) setting('uploads.max_image_kb', 5120),
                'max_document_kb' => (int) setting('uploads.max_document_kb', 8192),
                'max_gallery_images' => (int) setting('uploads.max_gallery_images', 12),
                'image_types' => ['image/jpeg', 'image/png', 'image/webp'],
                'document_types' => ['application/pdf', 'image/jpeg', 'image/png'],
            ],
            'api' => ['version' => 'v1'],
        ]])->header('Cache-Control', 'public, max-age=300');
    }
}
