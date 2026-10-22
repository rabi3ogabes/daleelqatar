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
                ['catering', 'تموين وضيافة', 'Catering'],
            ]],
            ['shopping-retail', 'تسوق ومحلات تجارية', 'Shopping & Retail', 'shopping-bag', true, 'متاجر الأزياء والإلكترونيات والعطور والمجوهرات وكل ما تحتاجه للتسوق في قطر.', 'Fashion, electronics, perfumes, jewellery and everything you need for shopping in Qatar.', [
                ['fashion-clothing', 'أزياء وملابس', 'Fashion & Clothing'],
                ['electronics', 'إلكترونيات', 'Electronics'],
                ['perfumes-oud', 'عطور وعود', 'Perfumes & Oud'],
                ['jewelry-gold', 'مجوهرات وذهب', 'Jewellery & Gold'],
                ['supermarkets', 'سوبرماركت', 'Supermarkets'],
                ['furniture-home', 'أثاث ومستلزمات منزلية', 'Furniture & Home'],
                ['tailoring', 'خياطة وتفصيل', 'Tailoring'],
                ['shoes-bags', 'أحذية وحقائب', 'Shoes & Bags'],
                ['gifts-flowers', 'هدايا وزهور', 'Gifts & Flowers'],
                ['kids-toys', 'مستلزمات أطفال وألعاب', 'Kids & Toys'],
                ['shopping-malls', 'مراكز تسوق', 'Shopping Malls'],
            ]],
            ['health-wellness', 'صحة وعافية', 'Health & Wellness', 'stethoscope', true, 'المستشفيات والعيادات والصيدليات ومراكز اللياقة في جميع مناطق قطر.', 'Hospitals, clinics, pharmacies and fitness centres across Qatar.', [
                ['hospitals', 'مستشفيات', 'Hospitals'],
                ['clinics', 'عيادات', 'Clinics'],
                ['dental', 'طب الأسنان', 'Dental'],
                ['pharmacies', 'صيدليات', 'Pharmacies'],
                ['fitness-gyms', 'لياقة وأندية رياضية', 'Fitness & Gyms'],
                ['physiotherapy', 'علاج طبيعي', 'Physiotherapy'],
                ['medical-equipment', 'معدات ومستلزمات طبية', 'Medical Equipment'],
                ['opticians', 'نظارات وبصريات', 'Opticians'],
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
                ['petrol-stations', 'محطات وقود', 'Petrol Stations'],
            ]],
            ['home-services', 'خدمات منزلية', 'Home Services', 'house', true, 'التنظيف والصيانة والتكييف ونقل الأثاث وخدمات المنزل.', 'Cleaning, maintenance, air-conditioning, moving and home services.', [
                ['cleaning', 'تنظيف', 'Cleaning'],
                ['home-maintenance', 'صيانة منزلية', 'Home Maintenance'],
                ['ac-services', 'تكييف وتبريد', 'AC Services'],
                ['pest-control', 'مكافحة حشرات', 'Pest Control'],
                ['movers', 'نقل أثاث', 'Movers'],
                ['landscaping', 'حدائق وتنسيق', 'Landscaping'],
                ['laundry', 'مغاسل وكي', 'Laundry & Dry Cleaning'],
            ]],
            ['professional-services', 'خدمات مهنية', 'Professional Services', 'briefcase', true, 'المحاماة والمحاسبة والاستشارات والترجمة والتسويق.', 'Legal, accounting, consulting, translation and marketing services.', [
                ['legal', 'محاماة واستشارات قانونية', 'Legal Services'],
                ['accounting', 'محاسبة وتدقيق', 'Accounting & Audit'],
                ['business-consulting', 'استشارات إدارية', 'Business Consulting'],
                ['translation', 'ترجمة', 'Translation'],
                ['marketing-advertising', 'تسويق وإعلان', 'Marketing & Advertising'],
                ['recruitment', 'توظيف', 'Recruitment'],
                ['printing', 'طباعة', 'Printing'],
                ['pro-services', 'تعقيب ومعاملات', 'PRO & Typing Services'],
                ['business-centers', 'مراكز أعمال', 'Business Centres'],
                ['media-publishing', 'إعلام ونشر', 'Media & Publishing'],
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
                ['parks-attractions', 'حدائق ومعالم سياحية', 'Parks & Attractions'],
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
                ['telecom', 'اتصالات', 'Telecommunications'],
                ['security-systems', 'أنظمة أمن ومراقبة', 'Security Systems'],
            ]],
            ['construction', 'مقاولات وإنشاءات', 'Construction', 'hard-hat', true, 'المقاولون ومواد البناء والاستشارات الهندسية والتصميم الداخلي.', 'Contractors, building materials, engineering and interior design.', [
                ['contractors', 'مقاولون', 'Contractors'],
                ['building-materials', 'مواد بناء', 'Building Materials'],
                ['engineering-consultancy', 'استشارات هندسية', 'Engineering Consultancy'],
                ['interior-design', 'تصميم داخلي', 'Interior Design'],
                ['aluminium-glass', 'ألمنيوم وزجاج', 'Aluminium & Glass'],
                ['mep-electrical', 'أعمال كهربائية وميكانيكية', 'MEP & Electrical'],
                ['renovation', 'ترميم وتشطيبات', 'Renovation & Fit-out'],
            ]],
            ['events-entertainment', 'فعاليات وترفيه', 'Events & Entertainment', 'party-popper', true, 'تنظيم الفعاليات وقاعات الأفراح ومراكز الترفيه والتصوير.', 'Event planning, wedding venues, entertainment and photography.', [
                ['event-planning', 'تنظيم فعاليات', 'Event Planning'],
                ['wedding-venues', 'قاعات أفراح', 'Wedding Venues'],
                ['entertainment-centers', 'مراكز ترفيه', 'Entertainment Centres'],
                ['photography', 'تصوير', 'Photography'],
                ['event-equipment', 'معدات وتجهيزات فعاليات', 'Event Equipment & Rentals'],
            ]],
            ['trading-contracting', 'تجارة ومقاولات', 'Trading & Contracting', 'briefcase-business', true, 'شركات التجارة العامة والاستيراد والتصدير وتجارة الجملة والمقاولات العامة في قطر.', 'General trading, import and export, wholesale and general contracting companies in Qatar.', [
                ['general-trading', 'تجارة عامة', 'General Trading'],
                ['import-export', 'استيراد وتصدير', 'Import & Export'],
                ['wholesale', 'تجارة الجملة', 'Wholesale'],
                ['general-contracting', 'مقاولات عامة', 'General Contracting'],
            ]],
            ['industry-manufacturing', 'الصناعة والمعدات', 'Industry & Manufacturing', 'factory', true, 'المصانع والمعدات الصناعية والكيماويات والنفط والغاز ومعدات السلامة.', 'Factories, industrial equipment, chemicals, oil and gas, and safety equipment.', [
                ['industrial-equipment', 'معدات صناعية', 'Industrial Equipment'],
                ['chemicals', 'كيماويات', 'Chemicals'],
                ['plastics-packaging', 'بلاستيك وتغليف', 'Plastics & Packaging'],
                ['steel-metal', 'حديد ومعادن', 'Steel & Metal'],
                ['food-manufacturing', 'صناعات غذائية', 'Food Manufacturing'],
                ['oil-gas-energy', 'النفط والغاز والطاقة', 'Oil, Gas & Energy'],
                ['safety-equipment', 'معدات السلامة', 'Safety Equipment'],
            ]],
            ['logistics-transport', 'النقل والخدمات اللوجستية', 'Logistics & Transport', 'truck', true, 'الشحن والخدمات اللوجستية وشركات النقل والخدمات البحرية والبريد السريع.', 'Freight, logistics, transport companies, marine services and couriers.', [
                ['freight-logistics', 'شحن وخدمات لوجستية', 'Freight & Logistics'],
                ['transport-companies', 'شركات نقل', 'Transport Companies'],
                ['marine-shipping', 'خدمات بحرية وملاحة', 'Marine & Shipping'],
                ['courier-delivery', 'بريد سريع وتوصيل', 'Courier & Delivery'],
                ['airlines-aviation', 'طيران وخطوط جوية', 'Airlines & Aviation'],
            ]],
            ['finance-insurance', 'البنوك والتمويل والتأمين', 'Banks, Finance & Insurance', 'landmark', true, 'البنوك ومحلات الصرافة وشركات التأمين والاستثمار والتمويل.', 'Banks, exchange houses, insurance, investment and finance companies.', [
                ['banks', 'بنوك', 'Banks'],
                ['exchange-houses', 'صرافة وتحويل أموال', 'Exchange Houses'],
                ['insurance', 'تأمين', 'Insurance'],
                ['investment-finance', 'استثمار وتمويل', 'Investment & Finance'],
            ]],
            ['government-community', 'جهات حكومية ومجتمعية', 'Government & Community', 'building', false, 'الوزارات والجهات الحكومية والسفارات والقنصليات والجمعيات في قطر.', 'Ministries, government offices, embassies, consulates and associations in Qatar.', [
                ['government-offices', 'جهات حكومية', 'Government Offices'],
                ['embassies-consulates', 'سفارات وقنصليات', 'Embassies & Consulates'],
                ['non-profits', 'جمعيات ومؤسسات خيرية', 'Non-profits & Associations'],
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
