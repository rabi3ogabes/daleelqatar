<?php

namespace Database\Seeders;

use App\Enums\BusinessStatus;
use App\Enums\OfferStatus;
use App\Models\Area;
use App\Models\Business;
use App\Models\Category;
use App\Models\Offer;
use App\Services\BusinessManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * DEVELOPMENT SAMPLE DATA ONLY.
 *
 * Every record is flagged is_demo = true and labelled "Sample listing" on the
 * site. No contact details, ratings, reviews or verification badges are
 * fabricated. Remove everything with: php artisan daleel:purge-demo
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo data must never be seeded in production.');
        }

        $manager = app(BusinessManager::class);

        $samples = [
            // [subcategory, area, name_ar, name_en, tagline_ar, tagline_en, featured, hours, services]
            ['cafes', 'msheireb', 'مقهى سدرة', 'Sidra Coffee House', 'قهوة مختصة في قلب مشيرب', 'Specialty coffee in the heart of Msheireb', true, 'cafe', [['قهوة مختصة', 'Specialty coffee'], ['حلويات منزلية', 'Homemade desserts']]],
            ['arabic-cuisine', 'katara', 'مطعم البيت القطري', 'Al Bait Al Qatari Kitchen', 'أطباق قطرية تقليدية بلمسة عصرية', 'Traditional Qatari dishes with a modern touch', true, 'restaurant', [['مجبوس', 'Machboos'], ['هريس', 'Harees'], ['ثريد', 'Thareed']]],
            ['fine-dining', 'west-bay', 'مطعم لؤلؤة الخليج', 'Gulf Pearl Dining', 'تجربة طعام راقية مطلة على الكورنيش', 'Fine dining overlooking the Corniche', true, 'restaurant', [['قائمة تذوق', 'Tasting menu'], ['حجوزات خاصة', 'Private dining']]],
            ['bakeries-sweets', 'al-sadd', 'مخبز السنبلة', 'Sunbula Bakery', 'خبز طازج وحلويات شرقية يومياً', 'Fresh bread and Arabic sweets daily', false, 'bakery', [['خبز عربي', 'Arabic bread'], ['كنافة', 'Kunafa']]],
            ['perfumes-oud', 'the-pearl-qatar', 'دار العود الملكي', 'Royal Oud House', 'عطور شرقية وعود فاخر', 'Oriental perfumes and fine oud', true, 'retail', [['عطور مخصصة', 'Bespoke fragrances'], ['دهن العود', 'Oud oil']]],
            ['fashion-clothing', 'lusail', 'أتيليه لوسيل', 'Lusail Atelier', 'تصاميم عبايات وأزياء راقية', 'Couture abayas and modest fashion', false, 'retail', [['تفصيل', 'Tailoring'], ['تصاميم حصرية', 'Exclusive designs']]],
            ['electronics', 'al-gharrafa', 'تقنية بلس', 'Tech Plus Store', 'أحدث الأجهزة والإكسسوارات', 'The latest devices and accessories', false, 'retail', [['هواتف ذكية', 'Smartphones'], ['أجهزة لابتوب', 'Laptops']]],
            ['clinics', 'al-waab', 'عيادة الواحة الطبية', 'Al Waha Medical Clinic', 'رعاية صحية عائلية متكاملة', 'Comprehensive family healthcare', true, 'clinic', [['طب عام', 'General practice'], ['طب أطفال', 'Paediatrics']]],
            ['fitness-gyms', 'aspire-zone', 'نادي القمة الرياضي', 'Summit Fitness Club', 'لياقة متكاملة بإشراف مدربين محترفين', 'Complete fitness with professional coaches', false, 'gym', [['تدريب شخصي', 'Personal training'], ['حصص جماعية', 'Group classes']]],
            ['spa-massage', 'the-pearl-qatar', 'سبا الياسمين', 'Jasmine Spa', 'استرخاء وعناية في أجواء هادئة', 'Relaxation and care in a calm setting', false, 'salon', [['مساج', 'Massage'], ['حمام مغربي', 'Moroccan bath']]],
            ['car-repair', 'industrial-area', 'مركز الدقة لصيانة السيارات', 'Precision Auto Care', 'صيانة شاملة لجميع أنواع السيارات', 'Complete servicing for all makes', false, 'workshop', [['فحص شامل', 'Full inspection'], ['تغيير زيوت', 'Oil change']]],
            ['car-rental', 'old-airport', 'درب لتأجير السيارات', 'Darb Car Rental', 'تأجير يومي وشهري بأسعار مرنة', 'Daily and monthly rentals with flexible rates', false, 'office', [['تأجير يومي', 'Daily rental'], ['تأجير شهري', 'Monthly rental']]],
            ['ac-services', 'al-wakrah-city', 'برودة لخدمات التكييف', 'Baroda AC Services', 'تركيب وصيانة أنظمة التكييف', 'AC installation and maintenance', false, 'office', [['صيانة دورية', 'Routine maintenance'], ['تركيب', 'Installation']]],
            ['cleaning', 'al-duhail', 'لمسة نقاء', 'Pure Touch Cleaning', 'خدمات تنظيف احترافية للمنازل والمكاتب', 'Professional home and office cleaning', false, 'office', [['تنظيف عميق', 'Deep cleaning'], ['تنظيف مكاتب', 'Office cleaning']]],
            ['legal', 'al-dafna', 'مكتب الميزان للمحاماة', 'Al Mizan Law Office', 'استشارات قانونية للأفراد والشركات', 'Legal advice for individuals and companies', true, 'office', [['تأسيس شركات', 'Company formation'], ['عقود', 'Contracts']]],
            ['language-centers', 'education-city', 'معهد الأفق للغات', 'Horizon Language Institute', 'دورات لغات لجميع المستويات', 'Language courses for every level', false, 'office', [['اللغة الإنجليزية', 'English'], ['اللغة العربية لغير الناطقين', 'Arabic for non-native speakers']]],
            ['hotels', 'west-bay', 'فندق أبراج الخليج', 'Gulf Towers Hotel', 'إقامة فاخرة في قلب الخليج الغربي', 'Luxury stays in the heart of West Bay', true, 'allday', [['أجنحة فندقية', 'Suites'], ['قاعات اجتماعات', 'Meeting rooms']]],
            ['tours-safari', 'mesaieed', 'رحلات الكثبان', 'Dunes Desert Safari', 'رحلات سفاري إلى خور العديد', 'Desert safaris to the Inland Sea', false, 'office', [['سفاري', 'Desert safari'], ['تخييم', 'Camping']]],
            ['real-estate-agencies', 'lusail', 'وسيط لوسيل العقاري', 'Lusail Realty Partners', 'بيع وتأجير العقارات السكنية والتجارية', 'Residential and commercial sales and leasing', false, 'office', [['تأجير', 'Leasing'], ['بيع', 'Sales']]],
            ['software-it', 'msheireb', 'نواة للحلول الرقمية', 'Nawat Digital Solutions', 'تطوير تطبيقات ومنصات رقمية', 'App and digital platform development', true, 'office', [['تطوير تطبيقات', 'App development'], ['استضافة سحابية', 'Cloud hosting']]],
            ['interior-design', 'al-markhiya', 'استوديو مشربية للتصميم', 'Mashrabiya Design Studio', 'تصميم داخلي يجمع الأصالة والحداثة', 'Interiors that blend heritage and modernity', false, 'office', [['تصميم سكني', 'Residential design'], ['تصميم تجاري', 'Commercial design']]],
            ['photography', 'katara', 'عدسة الدوحة', 'Doha Lens Studio', 'تصوير المناسبات والمنتجات', 'Event and product photography', false, 'office', [['تصوير مناسبات', 'Event photography'], ['تصوير منتجات', 'Product photography']]],
            ['wedding-venues', 'al-khor-city', 'قاعة الريم', 'Al Reem Hall', 'قاعة أفراح ومناسبات فاخرة', 'An elegant venue for weddings and events', false, 'evening', [['حفلات زفاف', 'Weddings'], ['مؤتمرات', 'Conferences']]],
            ['supermarkets', 'umm-salal-mohammed', 'أسواق الخير', 'Al Khair Market', 'احتياجاتك اليومية بأسعار مناسبة', 'Everyday essentials at fair prices', false, 'market', [['خضار وفواكه', 'Fresh produce'], ['توصيل', 'Delivery']]],
        ];

        $hours = [
            'cafe' => ['06:30', '23:00'], 'restaurant' => ['12:00', '23:30'], 'bakery' => ['05:30', '22:00'],
            'retail' => ['10:00', '22:00'], 'clinic' => ['08:00', '21:00'], 'gym' => ['05:00', '23:59'],
            'salon' => ['10:00', '22:00'], 'workshop' => ['07:00', '19:00'], 'office' => ['08:00', '17:00'],
            'market' => ['07:00', '01:00'], 'evening' => ['16:00', '02:00'],
        ];

        foreach ($samples as $i => [$sub, $areaSlug, $nameAr, $nameEn, $tagAr, $tagEn, $featured, $hourType, $services]) {
            $category = Category::query()->where('slug', $sub)->firstOrFail();
            $area = Area::query()->where('slug', $areaSlug)->firstOrFail();
            $slug = Str::slug($nameEn);

            if (Business::query()->withTrashed()->where('slug', $slug)->exists()) {
                continue;
            }

            $week = [];
            for ($d = 0; $d <= 6; $d++) {
                if ($hourType === 'allday') {
                    $week[$d] = ['all_day' => true];
                } elseif ($hourType === 'office' && $d === 5) {
                    $week[$d] = ['closed' => true]; // Friday
                } elseif ($hourType === 'restaurant' && $d === 5) {
                    $week[$d] = ['periods' => [['opens' => '13:00', 'closes' => '23:59']]];
                } else {
                    $week[$d] = ['periods' => [['opens' => $hours[$hourType][0], 'closes' => $hours[$hourType][1]]]];
                }
            }

            $business = new Business([
                'category_id' => $category->id,
                'municipality_id' => $area->municipality_id,
                'area_id' => $area->id,
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'tagline_ar' => $tagAr,
                'tagline_en' => $tagEn,
                'description_ar' => "{$nameAr} — {$tagAr}. هذه قائمة تجريبية لأغراض التطوير فقط، ولا تمثل نشاطاً تجارياً حقيقياً.",
                'description_en' => "{$nameEn} — {$tagEn}. This is a sample listing for development purposes only and does not represent a real business.",
                'keywords' => $category->name_en.', '.$category->name_ar,
                'address_ar' => $area->name_ar.'، '.$area->municipality->name_ar,
                'address_en' => $area->name_en.', '.$area->municipality->name_en,
            ]);
            $business->slug = $slug;
            $business->forceFill([
                'status' => BusinessStatus::Approved,
                'is_demo' => true,
                'is_featured' => $featured,
                'approved_at' => now()->subDays(40 - $i),
                'published_at' => now()->subDays(40 - $i)->subHours($i),
                'created_at' => now()->subDays(45 - $i),
            ]);
            $business->save();

            $manager->syncHours($business, $week);
            $manager->syncServices($business, array_map(fn ($s) => ['name_ar' => $s[0], 'name_en' => $s[1]], $services));
        }

        // A couple of sample offers on demo listings.
        foreach ([['sidra-coffee-house', 'خصم 20% على القهوة المختصة', '20% off specialty coffee', '20%', '20% OFF'], ['royal-oud-house', 'هدية مع كل عطر مخصص', 'A gift with every bespoke fragrance', 'هدية', 'GIFT']] as [$bSlug, $tAr, $tEn, $lAr, $lEn]) {
            $b = Business::query()->where('slug', $bSlug)->first();
            if (! $b || Offer::query()->where('business_id', $b->id)->exists()) {
                continue;
            }
            $offer = new Offer([
                'title_ar' => $tAr, 'title_en' => $tEn,
                'description_ar' => 'عرض تجريبي لأغراض التطوير فقط.', 'description_en' => 'Sample offer for development purposes only.',
                'discount_label_ar' => $lAr, 'discount_label_en' => $lEn,
                'starts_at' => now()->subDays(2), 'ends_at' => now()->addDays(20), 'is_active' => true,
            ]);
            $offer->business_id = $b->id;
            $offer->slug = Str::slug($tEn);
            $offer->status = OfferStatus::Approved;
            $offer->is_featured = true;
            $offer->save();
        }
    }
}
