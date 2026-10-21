<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/** The directory taxonomy (reference data, editable from the admin dashboard). */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $tree = [
            ['restaurants-cafes', 'مطاعم ومقاهي', 'Restaurants & Cafés', 'utensils', true, 'اكتشف أفضل المطاعم والمقاهي في قطر، من المطبخ القطري الأصيل إلى المطاعم العالمية.', 'Discover the best restaurants and cafés in Qatar, from authentic Qatari cuisine to international dining.', [
                ['arabic-cuisine', 'مطاعم عربية', 'Arabic Cuisine'],
                ['international-cuisine', 'مطاعم عالمية', 'International Cuisine'],
                ['cafes', 'مقاهي', 'Cafés'],
                ['bakeries-sweets', 'مخابز وحلويات', 'Bakeries & Sweets'],
                ['fast-food', 'وجبات سريعة', 'Fast Food'],
                ['fine-dining', 'مطاعم فاخرة', 'Fine Dining'],
            ]],
            ['shopping-retail', 'تسوق ومحلات تجارية', 'Shopping & Retail', 'shopping-bag', true, 'متاجر الأزياء والإلكترونيات والعطور والمجوهرات وكل ما تحتاجه للتسوق في قطر.', 'Fashion, electronics, perfumes, jewellery and everything you need for shopping in Qatar.', [
                ['fashion-clothing', 'أزياء وملابس', 'Fashion & Clothing'],
                ['electronics', 'إلكترونيات', 'Electronics'],
                ['perfumes-oud', 'عطور وعود', 'Perfumes & Oud'],
                ['jewelry-gold', 'مجوهرات وذهب', 'Jewellery & Gold'],
                ['supermarkets', 'سوبرماركت', 'Supermarkets'],
                ['furniture-home', 'أثاث ومستلزمات منزلية', 'Furniture & Home'],
            ]],
            ['health-wellness', 'صحة وعافية', 'Health & Wellness', 'stethoscope', true, 'المستشفيات والعيادات والصيدليات ومراكز اللياقة في جميع مناطق قطر.', 'Hospitals, clinics, pharmacies and fitness centres across Qatar.', [
                ['hospitals', 'مستشفيات', 'Hospitals'],
                ['clinics', 'عيادات', 'Clinics'],
                ['dental', 'طب الأسنان', 'Dental'],
                ['pharmacies', 'صيدليات', 'Pharmacies'],
                ['fitness-gyms', 'لياقة وأندية رياضية', 'Fitness & Gyms'],
                ['physiotherapy', 'علاج طبيعي', 'Physiotherapy'],
            ]],
            ['beauty-salons', 'تجميل وصالونات', 'Beauty & Salons', 'scissors', true, 'صالونات التجميل والحلاقة ومراكز السبا والعناية.', 'Beauty salons, barbershops, spas and personal care.', [
                ['ladies-salons', 'صالونات نسائية', 'Ladies Salons'],
                ['barbershops', 'صالونات رجالية', 'Barbershops'],
                ['spa-massage', 'سبا ومساج', 'Spa & Massage'],
                ['cosmetic-clinics', 'عيادات تجميل', 'Cosmetic Clinics'],
            ]],
            ['automotive', 'سيارات', 'Automotive', 'car', true, 'معارض السيارات والصيانة والتأجير وقطع الغيار.', 'Car dealers, servicing, rentals and spare parts.', [
                ['car-dealers', 'معارض سيارات', 'Car Dealers'],
                ['car-repair', 'صيانة سيارات', 'Car Repair'],
                ['car-rental', 'تأجير سيارات', 'Car Rental'],
                ['car-wash', 'غسيل وتلميع سيارات', 'Car Wash & Detailing'],
                ['spare-parts', 'قطع غيار', 'Spare Parts'],
            ]],
            ['home-services', 'خدمات منزلية', 'Home Services', 'house', true, 'التنظيف والصيانة والتكييف ونقل الأثاث وخدمات المنزل.', 'Cleaning, maintenance, air-conditioning, moving and home services.', [
                ['cleaning', 'تنظيف', 'Cleaning'],
                ['home-maintenance', 'صيانة منزلية', 'Home Maintenance'],
                ['ac-services', 'تكييف وتبريد', 'AC Services'],
                ['pest-control', 'مكافحة حشرات', 'Pest Control'],
                ['movers', 'نقل أثاث', 'Movers'],
                ['landscaping', 'حدائق وتنسيق', 'Landscaping'],
            ]],
            ['professional-services', 'خدمات مهنية', 'Professional Services', 'briefcase', true, 'المحاماة والمحاسبة والاستشارات والترجمة والتسويق.', 'Legal, accounting, consulting, translation and marketing services.', [
                ['legal', 'محاماة واستشارات قانونية', 'Legal Services'],
                ['accounting', 'محاسبة وتدقيق', 'Accounting & Audit'],
                ['business-consulting', 'استشارات إدارية', 'Business Consulting'],
                ['translation', 'ترجمة', 'Translation'],
                ['marketing-advertising', 'تسويق وإعلان', 'Marketing & Advertising'],
                ['recruitment', 'توظيف', 'Recruitment'],
            ]],
            ['education-training', 'تعليم وتدريب', 'Education & Training', 'graduation-cap', true, 'المدارس والجامعات ومراكز التدريب ومعاهد اللغات.', 'Schools, universities, training centres and language institutes.', [
                ['schools', 'مدارس', 'Schools'],
                ['universities', 'جامعات وكليات', 'Universities & Colleges'],
                ['training-centers', 'مراكز تدريب', 'Training Centres'],
                ['language-centers', 'معاهد لغات', 'Language Centres'],
                ['nurseries', 'حضانات', 'Nurseries'],
            ]],
            ['hotels-tourism', 'فنادق وسياحة', 'Hotels & Tourism', 'hotel', true, 'الفنادق والشقق الفندقية ووكالات السفر والرحلات السياحية.', 'Hotels, serviced apartments, travel agencies and tours.', [
                ['hotels', 'فنادق', 'Hotels'],
                ['serviced-apartments', 'شقق فندقية', 'Serviced Apartments'],
                ['travel-agencies', 'وكالات سفر', 'Travel Agencies'],
                ['tours-safari', 'رحلات وسفاري', 'Tours & Desert Safari'],
            ]],
            ['real-estate', 'عقارات', 'Real Estate', 'building-2', true, 'المكاتب العقارية والمطورون وإدارة الأملاك.', 'Real estate agencies, developers and property management.', [
                ['real-estate-agencies', 'مكاتب عقارية', 'Real Estate Agencies'],
                ['property-developers', 'مطورون عقاريون', 'Property Developers'],
                ['property-management', 'إدارة أملاك', 'Property Management'],
            ]],
            ['technology', 'تقنية', 'Technology', 'cpu', true, 'شركات البرمجيات وتقنية المعلومات والتسويق الرقمي والأجهزة.', 'Software, IT services, digital agencies and devices.', [
                ['software-it', 'برمجيات وتقنية معلومات', 'Software & IT'],
                ['mobile-repair', 'هواتف وصيانة', 'Mobile & Repair'],
                ['digital-agencies', 'تصميم مواقع وتسويق رقمي', 'Web & Digital Agencies'],
                ['it-equipment', 'أجهزة ومعدات', 'IT Equipment'],
            ]],
            ['construction', 'مقاولات وإنشاءات', 'Construction', 'hard-hat', true, 'المقاولون ومواد البناء والاستشارات الهندسية والتصميم الداخلي.', 'Contractors, building materials, engineering and interior design.', [
                ['contractors', 'مقاولون', 'Contractors'],
                ['building-materials', 'مواد بناء', 'Building Materials'],
                ['engineering-consultancy', 'استشارات هندسية', 'Engineering Consultancy'],
                ['interior-design', 'تصميم داخلي', 'Interior Design'],
            ]],
            ['events-entertainment', 'فعاليات وترفيه', 'Events & Entertainment', 'party-popper', true, 'تنظيم الفعاليات وقاعات الأفراح ومراكز الترفيه والتصوير.', 'Event planning, wedding venues, entertainment and photography.', [
                ['event-planning', 'تنظيم فعاليات', 'Event Planning'],
                ['wedding-venues', 'قاعات أفراح', 'Wedding Venues'],
                ['entertainment-centers', 'مراكز ترفيه', 'Entertainment Centres'],
                ['photography', 'تصوير', 'Photography'],
            ]],
            ['other-businesses', 'أعمال أخرى', 'Other Businesses', 'layout-grid', false, 'أعمال وخدمات متنوعة أخرى في قطر.', 'Other businesses and services in Qatar.', []],
        ];

        foreach ($tree as $i => [$slug, $ar, $en, $icon, $featured, $dAr, $dEn, $children]) {
            $parent = Category::query()->updateOrCreate(['slug' => $slug], [
                'parent_id' => null, 'name_ar' => $ar, 'name_en' => $en, 'icon' => $icon,
                'description_ar' => $dAr, 'description_en' => $dEn,
                'sort_order' => $i, 'is_active' => true, 'is_featured' => $featured,
            ]);
            foreach ($children as $j => [$cSlug, $cAr, $cEn]) {
                Category::query()->updateOrCreate(['slug' => $cSlug], [
                    'parent_id' => $parent->id, 'name_ar' => $cAr, 'name_en' => $cEn, 'icon' => $icon,
                    'sort_order' => $j, 'is_active' => true, 'is_featured' => false,
                ]);
            }
        }
    }
}
