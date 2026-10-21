<?php

declare(strict_types=1);

namespace App\Support\Import;

use Illuminate\Support\Str;

/**
 * Recognises spreadsheet columns by their header text (English or Arabic) and
 * finds the header row even when a file starts with title rows.
 */
final class ColumnMapper
{
    /** Field => header aliases (compared after normalisation). Order matters: first match wins. */
    public const FIELDS = [
        'name' => ['company', 'company name', 'business name', 'business', 'name', 'name en', 'english name', 'company name en', 'organization', 'organisation', 'establishment', 'listing name', 'title', 'store name', 'shop name', 'اسم الشركة', 'الاسم', 'اسم النشاط', 'الشركة', 'اسم المنشأة'],
        'name_ar' => ['arabic name', 'name ar', 'company ar', 'company name ar', 'name arabic', 'الاسم بالعربية', 'الاسم العربي', 'الاسم عربي'],
        'category' => ['category', 'categories', 'business category', 'type', 'business type', 'industry', 'activity', 'sector', 'التصنيف', 'الفئة', 'النشاط', 'القطاع'],
        'tags' => ['tags keywords', 'tags', 'keywords', 'services', 'products', 'الكلمات المفتاحية', 'الخدمات'],
        'phone' => ['primary phone', 'phone', 'telephone', 'tel', 'phone number', 'contact number', 'main phone', 'contact phone', 'الهاتف', 'رقم الهاتف', 'هاتف'],
        'mobile' => ['mobile whatsapp', 'mobile', 'whatsapp', 'mobile number', 'cell', 'cell phone', 'الجوال', 'واتساب', 'الموبايل'],
        'landline' => ['landline', 'office phone', 'land line'],
        'other_phones' => ['other numbers', 'other phones', 'additional phones', 'phones', 'more phones'],
        'email' => ['primary email', 'email', 'e mail', 'email address', 'mail', 'البريد الالكتروني', 'البريد الإلكتروني', 'البريد'],
        'other_emails' => ['other emails', 'additional emails', 'emails'],
        'website' => ['website', 'web', 'url', 'site', 'web site', 'homepage', 'الموقع', 'الموقع الالكتروني'],
        'social' => ['social media', 'social', 'social links', 'socials', 'social profiles', 'التواصل الاجتماعي'],
        'address' => ['address', 'full address', 'street address', 'address from card', 'location', 'location website', 'العنوان', 'الموقع الجغرافي'],
        'city' => ['city', 'area', 'district', 'town', 'zone', 'municipality', 'region', 'المدينة', 'المنطقة', 'البلدية'],
        'po_box' => ['p o box', 'po box', 'pobox', 'p o b', 'ص ب', 'صندوق البريد'],
        'latitude' => ['latitude', 'lat', 'خط العرض'],
        'longitude' => ['longitude', 'lng', 'lon', 'long', 'خط الطول'],
        'map_precision' => ['map precision', 'precision', 'location precision', 'geo precision'],
        'hours' => ['opening hours', 'hours', 'working hours', 'timing', 'timings', 'business hours', 'open hours', 'ساعات العمل', 'أوقات العمل'],
        'description' => ['description', 'about', 'overview', 'company description', 'profile', 'الوصف', 'نبذة', 'نبذة عن الشركة'],
        'source_url' => ['listing page', 'source url', 'page url', 'profile url', 'listing url', 'source', 'link'],
        'external_id' => ['listing id', 'id', 'record id', 'external id', 'company id'],
    ];

    /** @return array<string, string> field => translated label */
    public static function labels(): array
    {
        return collect(array_keys(self::FIELDS))->mapWithKeys(fn ($f) => [$f => __('imports.fields.'.$f)])->all();
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, int> field => column index
     */
    public static function detect(array $headers): array
    {
        $normalized = array_map(fn ($h) => self::normalize($h), $headers);
        $mapping = [];
        foreach (self::FIELDS as $field => $aliases) {
            foreach ($normalized as $i => $header) {
                if ($header === '' || in_array($i, $mapping, true)) {
                    continue;
                }
                if (in_array($header, $aliases, true)) {
                    $mapping[$field] = $i;
                    break;
                }
            }
        }

        // Second pass: loose "contains" matches for fields still missing (e.g. "Company Name (English)").
        foreach (['name', 'phone', 'email', 'website', 'address', 'category', 'city', 'description'] as $field) {
            if (isset($mapping[$field])) {
                continue;
            }
            foreach ($normalized as $i => $header) {
                if ($header === '' || in_array($i, $mapping, true)) {
                    continue;
                }
                foreach (self::FIELDS[$field] as $alias) {
                    if (mb_strlen($alias) >= 4 && str_contains(' '.$header.' ', ' '.$alias.' ')) {
                        $mapping[$field] = $i;

                        continue 3;
                    }
                }
            }
        }

        return $mapping;
    }

    /** Score a candidate header row: the number of recognised fields, 0 without a name column. */
    public static function score(array $row): int
    {
        $mapping = self::detect($row);

        return isset($mapping['name']) ? count($mapping) : 0;
    }

    public static function normalize(string $header): string
    {
        $h = Str::lower(trim($header));
        $h = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $h) ?? $h;

        return trim(preg_replace('/\s+/', ' ', $h) ?? $h);
    }
}
