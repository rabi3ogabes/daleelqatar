<?php

namespace Tests\Feature\Directory;

use App\Support\Enrichment\AiWriter;

/** A predictable stand-in for the AI assistant in tests. */
class FakeAiWriter implements AiWriter
{
    /** @var array<string, array{category: string, confidence: int}> business name => verdict */
    public static array $categories = [];

    /** @var list<array{origin: string, text: string}> what the profile writer was given */
    public static array $profiles = [];

    public function available(): bool
    {
        return true;
    }

    public function arabicNames(array $businesses): array
    {
        $known = ['Al Ansari Furniture Centre' => 'مركز الأنصاري للأثاث', 'Pearl Trading W.L.L' => 'اللؤلؤة للتجارة'];

        return collect($businesses)->mapWithKeys(fn ($b) => [$b['id'] => $known[$b['name']] ?? 'شركة'])->all();
    }

    public function profileFromWebsite(string $name, ?string $category, array $pages, string $origin = 'website'): array
    {
        self::$profiles[] = ['origin' => $origin, 'text' => implode("\n", $pages)];
        if ($origin === 'directory') {
            return [
                'found' => true,
                'description_en' => 'Supplier of building materials and construction tools in Doha.',
                'description_ar' => 'مورّد لمواد البناء وأدوات الإنشاء في الدوحة.',
                'tagline_en' => '',
                'tagline_ar' => '',
                'services' => [],
            ];
        }

        return [
            'found' => str_contains(implode(' ', $pages), 'majlis'),
            'description_en' => 'Furniture showroom in Najma, Doha, supplying majlis seating, bedrooms and office furniture.',
            'description_ar' => 'معرض أثاث في نجمة بالدوحة يوفر المجالس وغرف النوم وأثاث المكاتب.',
            'tagline_en' => 'Home and office furniture since 1995',
            'tagline_ar' => 'أثاث المنازل والمكاتب منذ 1995',
            'services' => [
                ['name_en' => 'Custom majlis seating', 'name_ar' => 'مجالس حسب الطلب'],
                ['name_en' => 'Office furniture', 'name_ar' => 'أثاث مكتبي'],
                ['name_en' => 'Curtains and upholstery', 'name_ar' => 'ستائر وتنجيد'],
            ],
        ];
    }

    public function categorize(array $businesses, array $taxonomy): array
    {
        $out = [];
        foreach ($businesses as $b) {
            if (isset(self::$categories[$b['name']])) {
                $out[$b['id']] = self::$categories[$b['name']] + ['secondary' => [], 'reason_en' => 'The name says what it does.', 'reason_ar' => 'الاسم يوضح النشاط.'];
            }
        }

        return $out;
    }
}
