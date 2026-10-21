<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Municipality;
use Illuminate\Database\Seeder;

/**
 * Qatar's eight municipalities and their principal areas. This is reference
 * data (not sample data) and remains fully editable from the admin dashboard.
 */
class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['doha', 'الدوحة', 'Doha', 25.2854, 51.5310, [
                ['west-bay', 'الخليج الغربي', 'West Bay', true],
                ['al-dafna', 'الدفنة', 'Al Dafna'],
                ['msheireb', 'مشيرب', 'Msheireb', true],
                ['al-sadd', 'السد', 'Al Sadd'],
                ['bin-mahmoud', 'بن محمود', 'Bin Mahmoud'],
                ['fereej-bin-omran', 'فريج بن عمران', 'Fereej Bin Omran'],
                ['al-hilal', 'الهلال', 'Al Hilal'],
                ['old-airport', 'المطار القديم', 'Old Airport'],
                ['najma', 'نجمة', 'Najma'],
                ['al-mansoura', 'المنصورة', 'Al Mansoura'],
                ['the-pearl-qatar', 'اللؤلؤة - قطر', 'The Pearl-Qatar', true],
                ['katara', 'كتارا', 'Katara'],
                ['madinat-khalifa', 'مدينة خليفة', 'Madinat Khalifa'],
                ['al-markhiya', 'المرخية', 'Al Markhiya'],
                ['al-duhail', 'الدحيل', 'Al Duhail'],
                ['onaiza', 'عنيزة', 'Onaiza'],
                ['abu-hamour', 'أبو هامور', 'Abu Hamour'],
                ['al-thumama', 'الثمامة', 'Al Thumama'],
                ['nuaija', 'النعيجة', 'Nuaija'],
                ['al-muntazah', 'المنتزه', 'Al Muntazah'],
            ]],
            ['lusail-al-daayen', 'الضعاين', 'Al Daayen', 25.5784, 51.4823, [
                ['lusail', 'لوسيل', 'Lusail', true],
                ['simaisma', 'سميسمة', 'Simaisma'],
                ['umm-qarn', 'أم قرن', 'Umm Qarn'],
                ['al-kheesa', 'الخيسة', 'Al Kheesa'],
                ['leabaib', 'لعبيب', 'Leabaib'],
                ['rawdat-al-hamama', 'روضة الحمامة', 'Rawdat Al Hamama'],
                ['wadi-al-banat', 'وادي البنات', 'Wadi Al Banat'],
            ]],
            ['al-rayyan', 'الريان', 'Al Rayyan', 25.2919, 51.4244, [
                ['education-city', 'المدينة التعليمية', 'Education City', true],
                ['al-gharrafa', 'الغرافة', 'Al Gharrafa'],
                ['al-waab', 'الوعب', 'Al Waab'],
                ['aspire-zone', 'أسباير زون', 'Aspire Zone'],
                ['muaither', 'معيذر', 'Muaither'],
                ['al-luqta', 'اللقطة', 'Al Luqta'],
                ['baaya', 'بعيا', 'Baaya'],
                ['al-aziziya', 'العزيزية', 'Al Aziziya'],
                ['ain-khaled', 'عين خالد', 'Ain Khaled'],
                ['industrial-area', 'المنطقة الصناعية', 'Industrial Area'],
                ['old-al-rayyan', 'الريان القديم', 'Old Al Rayyan'],
                ['al-wajba', 'الوجبة', 'Al Wajba'],
                ['al-messila', 'المسيلة', 'Al Messila'],
            ]],
            ['al-wakrah', 'الوكرة', 'Al Wakrah', 25.1659, 51.6038, [
                ['al-wakrah-city', 'مدينة الوكرة', 'Al Wakrah City'],
                ['al-wukair', 'الوكير', 'Al Wukair'],
                ['mesaieed', 'مسيعيد', 'Mesaieed'],
                ['al-mashaf', 'المشاف', 'Al Mashaf'],
            ]],
            ['al-khor', 'الخور', 'Al Khor', 25.6839, 51.5058, [
                ['al-khor-city', 'مدينة الخور', 'Al Khor City'],
                ['al-thakhira', 'الذخيرة', 'Al Thakhira'],
                ['ras-laffan', 'راس لفان', 'Ras Laffan'],
            ]],
            ['umm-salal', 'أم صلال', 'Umm Salal', 25.4152, 51.4065, [
                ['umm-salal-mohammed', 'أم صلال محمد', 'Umm Salal Mohammed'],
                ['umm-salal-ali', 'أم صلال علي', 'Umm Salal Ali'],
                ['al-kharaitiyat', 'الخريطيات', 'Al Kharaitiyat'],
                ['izghawa', 'إزغوى', 'Izghawa'],
                ['umm-ebairiya', 'أم عبيرية', 'Umm Ebairiya'],
            ]],
            ['al-shamal', 'الشمال', 'Al Shamal', 26.1293, 51.2009, [
                ['madinat-al-shamal', 'مدينة الشمال', 'Madinat Al Shamal'],
                ['al-ruwais', 'الرويس', 'Al Ruwais'],
                ['fuwairit', 'فويرط', 'Fuwairit'],
                ['al-zubarah', 'الزبارة', 'Al Zubarah'],
                ['abu-dhalouf', 'أبو ظلوف', 'Abu Dhalouf'],
            ]],
            ['al-shahaniya', 'الشحانية', 'Al Shahaniya', 25.3946, 51.2015, [
                ['al-shahaniya-town', 'الشحانية', 'Al Shahaniya Town'],
                ['dukhan', 'دخان', 'Dukhan'],
                ['al-jemailiya', 'الجميلية', 'Al Jemailiya'],
                ['umm-bab', 'أم باب', 'Umm Bab'],
            ]],
        ];

        // Order on the site: Doha, Lusail/Al Daayen, Al Rayyan, …
        foreach ($data as $i => [$slug, $ar, $en, $lat, $lng, $areas]) {
            $slug = $slug === 'lusail-al-daayen' ? 'al-daayen' : $slug;
            $municipality = Municipality::query()->updateOrCreate(['slug' => $slug], [
                'name_ar' => $ar, 'name_en' => $en, 'latitude' => $lat, 'longitude' => $lng,
                'sort_order' => $i, 'is_active' => true, 'is_featured' => true,
            ]);

            foreach ($areas as $j => $area) {
                Area::query()->updateOrCreate(['slug' => $area[0]], [
                    'municipality_id' => $municipality->id,
                    'name_ar' => $area[1], 'name_en' => $area[2],
                    'sort_order' => $j, 'is_active' => true, 'is_featured' => (bool) ($area[3] ?? false),
                ]);
            }
        }
    }
}
