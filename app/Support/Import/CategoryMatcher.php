<?php

declare(strict_types=1);

namespace App\Support\Import;

use App\Models\Category;
use Illuminate\Support\Str;

/**
 * Maps free-text categories from other directories ("Car Rental Companies",
 * "Typing, Translation, Attestation & PRO Services" …) onto this directory's
 * taxonomy using keyword rules, most specific first. Source categories are
 * tried first, then tags, then the company name.
 */
final class CategoryMatcher
{
    public const FALLBACK = 'other-businesses';

    /** slug => keywords (whole-word, lower case). Order = priority. */
    private const RULES = [
        'general-contracting' => ['trading & contracting', 'trading and contracting', 'trading contracting', 'general contracting'],
        'car-rental' => ['car rental', 'rent a car', 'car hire', 'limousine', 'chauffeur', 'car service', 'car leasing', 'vehicle rental'],
        'car-wash' => ['car wash', 'detailing', 'car polish', 'car care', 'window tinting', 'tinting'],
        'spare-parts' => ['spare parts', 'auto parts', 'car parts', 'automobile spare parts', 'tyres', 'tires', 'batteries', 'car accessories', 'lubricants'],
        'car-dealers' => ['car dealer', 'car dealers', 'showroom', 'vehicle sales', 'used cars', 'automobile dealers', 'motors', 'car sales'],
        'car-repair' => ['garage', 'car repair', 'auto repair', 'workshop', 'vehicle services', 'body shop', 'car maintenance', 'auto service'],
        'cosmetic-clinics' => ['cosmetic clinic', 'dermatology', 'aesthetic', 'laser clinic', 'plastic surgery'],
        'dental' => ['dental', 'dentist', 'dentistry', 'orthodontic', 'orthodontics'],
        'pharmacies' => ['pharmacy', 'pharmacies', 'chemist', 'drug store'],
        'hospitals' => ['hospital', 'hospitals'],
        'physiotherapy' => ['physiotherapy', 'physio', 'rehabilitation'],
        'medical-equipment' => ['medical equipment', 'medical supplies', 'surgical', 'laboratory equipment', 'medical devices'],
        'clinics' => ['clinic', 'clinics', 'doctors', 'doctor', 'polyclinic', 'medical center', 'medical centre', 'healthcare', 'health care', 'diagnostic', 'laboratory', 'optical', 'opticians', 'medical'],
        'fitness-gyms' => ['gym', 'gyms', 'fitness', 'yoga', 'martial arts', 'sports club', 'padel', 'swimming', 'sports & recreation', 'sports and recreation', 'sports'],
        'barbershops' => ['barber', 'barbershop', 'gents salon', 'men salon', 'mens salon'],
        'spa-massage' => ['spa', 'massage', 'sauna', 'hammam', 'wellness'],
        'ladies-salons' => ['ladies salon', 'beauty salon', 'beauty salons', 'beauty professionals', 'beauty centre', 'beauty center', 'nail', 'nails', 'henna', 'makeup', 'make up', 'bridal'],
        'beauty-salons' => ['beauty', 'cosmetics', 'beauty products', 'salon', 'salons'],
        'catering' => ['catering', 'caterers', 'canteen'],
        'bakeries-sweets' => ['bakery', 'bakeries', 'bakers', 'sweets', 'pastry', 'patisserie', 'cakes', 'chocolate', 'chocolates', 'dessert', 'desserts'],
        'cafes' => ['cafe', 'cafes', 'café', 'cafés', 'coffee', 'karak', 'coffee shop', 'juice', 'juices'],
        'fast-food' => ['fast food', 'burger', 'burgers', 'pizza', 'fried chicken', 'sandwich', 'sandwiches', 'shawarma'],
        'fine-dining' => ['fine dining', 'steakhouse'],
        'arabic-cuisine' => ['arabic restaurant', 'lebanese', 'syrian', 'yemeni', 'mandi', 'qatari cuisine', 'arabic cuisine', 'turkish restaurant'],
        'international-cuisine' => ['restaurant', 'restaurants', 'cuisine', 'dining', 'food court', 'grill', 'kitchen restaurant'],
        'supermarkets' => ['supermarket', 'supermarkets', 'hypermarket', 'grocery', 'groceries', 'food retailers', 'minimarket', 'mini market', 'cold store', 'foodstuff', 'foodstuffs'],
        'perfumes-oud' => ['perfume', 'perfumes', 'oud', 'fragrance', 'fragrances', 'incense', 'bakhoor'],
        'jewelry-gold' => ['jewellery', 'jewelry', 'jewellers', 'jewelers', 'gold', 'diamond', 'diamonds', 'watches', 'jewellery & watches'],
        'tailoring' => ['tailoring', 'tailor', 'tailors', 'uniform', 'uniforms', 'abaya', 'abayas'],
        'shoes-bags' => ['shoes', 'footwear', 'bags', 'handbags', 'leather goods', 'kids shoes'],
        'gifts-flowers' => ['gifts', 'gift', 'flowers', 'florist', 'florists', 'arts and crafts', 'handicrafts'],
        'kids-toys' => ['toys', 'baby', 'babies', 'kids', 'children'],
        'fashion-clothing' => ['clothing', 'fashion', 'boutique', 'boutiques', 'apparel', 'garments', 'textiles', 'fabric', 'fabrics', 'readymade', 'readymades', 'lingerie'],
        'mobile-repair' => ['mobile', 'mobiles', 'mobile phones', 'mobile & accessories', 'phone repair'],
        'electronics' => ['electronics', 'electrical goods', 'home appliances', 'appliances', 'audio visual', 'electronic equipment', 'consumer electronics'],
        'furniture-home' => ['furniture', 'home and garden', 'home furnishing', 'furnishing', 'curtains', 'carpets', 'mattress', 'mattresses', 'household', 'kitchen equipment', 'kitchens', 'home decor'],
        'security-systems' => ['cctv', 'security systems', 'surveillance', 'access control', 'fire alarm', 'security services', 'security guards'],
        'telecom' => ['telecommunication', 'telecommunications', 'telecom', 'communications', 'radio communication', 'internet service'],
        'digital-agencies' => ['digital marketing', 'web design', 'website design', 'web development', 'seo', 'social media marketing', 'digital agency', 'app development'],
        'software-it' => ['software', 'information technology', 'it services', 'it consultancy', 'it solutions', 'computer services', 'erp', 'web hosting', 'technology companies', 'it company'],
        'it-equipment' => ['computer', 'computers', 'it equipment', 'printers', 'networking', 'servers', 'laptops'],
        'laundry' => ['laundry', 'laundries', 'dry clean', 'dry cleaning', 'dry cleaners', 'ironing'],
        'pest-control' => ['pest control', 'pest', 'fumigation'],
        'movers' => ['movers', 'moving', 'relocation', 'packers', 'removals', 'packing and moving'],
        'landscaping' => ['landscaping', 'landscape', 'gardening', 'garden', 'irrigation', 'swimming pool', 'swimming pools', 'plant nursery'],
        'ac-services' => ['air conditioning', 'air conditioner', 'hvac', 'refrigeration', 'a/c', 'chillers', 'ac repair', 'ac services'],
        'cleaning' => ['cleaning', 'cleaners', 'hygiene', 'janitorial', 'cleaning equipment'],
        'home-maintenance' => ['maintenance', 'plumbing', 'plumber', 'handyman', 'carpentry', 'carpenter', 'waterproofing', 'facility management', 'facilities management'],
        'legal' => ['law firm', 'lawyer', 'lawyers', 'legal', 'advocates', 'attorneys', 'law office'],
        'accounting' => ['accounting', 'accountant', 'accountants', 'audit', 'auditing', 'auditors', 'tax', 'bookkeeping', 'chartered accountants'],
        'translation' => ['translation', 'translator', 'translators'],
        'pro-services' => ['typing', 'attestation', 'pro services', 'pro service', 'document clearing', 'visa services', 'government services', 'typing centre'],
        'printing' => ['printing', 'printers press', 'print', 'printing press', 'advertising & printing'],
        'marketing-advertising' => ['advertising', 'marketing', 'branding', 'signage', 'signs', 'public relations', 'pr agency', 'media', 'exhibition stands'],
        'recruitment' => ['recruitment', 'manpower', 'staffing', 'employment agency', 'human resources', 'hr services', 'outsourcing'],
        'business-centers' => ['business centre', 'business center', 'business centres', 'business centers', 'coworking', 'co-working', 'office space', 'serviced office'],
        'business-consulting' => ['consultants', 'consulting', 'consultancy', 'business management', 'business development', 'project management', 'business setup', 'company formation', 'management consulting'],
        'nurseries' => ['nursery', 'nurseries', 'kindergarten', 'preschool', 'pre school', 'daycare', 'day care'],
        'universities' => ['university', 'universities', 'college', 'colleges'],
        'language-centers' => ['language', 'languages', 'english courses', 'arabic courses', 'language centre'],
        'schools' => ['school', 'schools'],
        'training-centers' => ['training', 'institute', 'courses', 'tutoring', 'tuition', 'education', 'education centre', 'education center', 'driving school', 'music school', 'academy'],
        'serviced-apartments' => ['serviced apartment', 'serviced apartments', 'hotel apartments', 'furnished apartments'],
        'hotels' => ['hotel', 'hotels', 'resort', 'resorts', 'inn'],
        'tours-safari' => ['tours', 'safari', 'desert safari', 'dhow', 'excursion', 'excursions', 'tour operator'],
        'travel-agencies' => ['travel agent', 'travel agents', 'travel agency', 'travels', 'tourism', 'ticketing', 'umrah', 'hajj', 'travel & tourism'],
        'property-developers' => ['property development', 'property developer', 'property developers', 'real estate development', 'developers'],
        'property-management' => ['property management', 'building management'],
        'real-estate-agencies' => ['real estate', 'realty', 'real estate agency', 'property', 'properties', 'estate agents'],
        'aluminium-glass' => ['aluminium', 'aluminum', 'glass', 'glazing', 'curtain wall', 'mirror'],
        'mep-electrical' => ['electrical contracting', 'electromechanical', 'electro mechanical', 'mep', 'electrical service', 'electrical services', 'electrical works', 'electrical & solar', 'solar', 'lighting', 'switchgear', 'electrical contractors', 'electrical service companies', 'mechanical'],
        'renovation' => ['renovation', 'fit out', 'fit-out', 'fitout', 'decoration', 'decor', 'gypsum', 'false ceiling', 'flooring', 'tiles', 'marble', 'painting'],
        'interior-design' => ['interior design', 'interiors', 'interior designers'],
        'engineering-consultancy' => ['engineering', 'engineers', 'architectural', 'architects', 'architecture', 'surveying', 'design consultants', 'geotechnical'],
        'building-materials' => ['building materials', 'construction materials', 'cement', 'concrete', 'ready mix', 'readymix', 'blocks', 'hardware', 'sanitary ware', 'sanitaryware', 'paints', 'timber', 'wood', 'plywood', 'insulation'],
        'contractors' => ['construction companies', 'construction services', 'construction', 'contracting', 'contractors', 'contractor', 'civil works', 'building contractors', 'construction & engineering', 'roads'],
        'banks' => ['bank', 'banks', 'banking', 'credit unions'],
        'exchange-houses' => ['exchange', 'money transfer', 'remittance', 'money exchange'],
        'insurance' => ['insurance', 'takaful', 'reinsurance'],
        'investment-finance' => ['investment', 'investments', 'finance', 'financial', 'financial activity', 'capital', 'holding', 'asset management', 'fund', 'funds', 'brokerage'],
        'courier-delivery' => ['courier', 'couriers', 'delivery', 'express delivery', 'postal'],
        'marine-shipping' => ['marine', 'shipping', 'ship', 'ships', 'boat', 'boats', 'yacht', 'yachts', 'maritime', 'vessel', 'vessels', 'port services', 'offshore'],
        'freight-logistics' => ['logistics', 'freight', 'cargo', 'forwarding', 'freight forwarding', 'customs clearance', 'warehousing', 'warehouse', 'storage', 'supply chain'],
        'transport-companies' => ['transport', 'transportation', 'bus', 'buses', 'trucking', 'haulage', 'taxi'],
        'chemicals' => ['chemical', 'chemicals', 'chemicals companies'],
        'plastics-packaging' => ['plastic', 'plastics', 'plastic products', 'packaging', 'paper products', 'cartons', 'carton'],
        'steel-metal' => ['steel', 'steel products', 'metal', 'metals', 'iron', 'scrap', 'fabrication', 'welding', 'metal works'],
        'food-manufacturing' => ['food manufacturing', 'food processing', 'food products', 'bottled water', 'dairy', 'poultry', 'meat', 'flour', 'food industries', 'food production'],
        'oil-gas-energy' => ['oil & gas', 'oil and gas', 'oil gas', 'petroleum', 'energy', 'energy suppliers', 'power generation', 'gas'],
        'safety-equipment' => ['safety', 'fire fighting', 'firefighting', 'fire protection', 'ppe', 'health and safety', 'safety equipment'],
        'industrial-equipment' => ['industrial equipment', 'industrial services', 'industrial supplies', 'machinery', 'equipment rental', 'heavy equipment', 'construction equipment', 'generators', 'pumps', 'valves', 'compressors', 'cranes', 'scaffolding', 'tools', 'bearings', 'hydraulic', 'industrial', 'banking equipment'],
        'wedding-venues' => ['wedding', 'weddings', 'ballroom', 'banquet', 'wedding hall'],
        'event-equipment' => ['event equipment', 'tents', 'tent', 'stage', 'party rental', 'event rentals'],
        'event-planning' => ['event services', 'events', 'event management', 'event planner', 'event planning', 'exhibition', 'exhibitions', 'conference', 'entertainment & events'],
        'photography' => ['photography', 'photographer', 'photographers', 'photo studio', 'videography', 'video production', 'production house'],
        'entertainment-centers' => ['entertainment', 'amusement', 'play area', 'cinema', 'bowling', 'games', 'gaming'],
        'import-export' => ['import', 'export', 'import and export', 'import and export agents', 'importers', 'exporters'],
        'wholesale' => ['wholesale', 'wholesalers', 'wholesale traders', 'distributors', 'distribution', 'retail & wholesale trade', 'suppliers'],
        'general-trading' => ['trading', 'traders', 'general trading', 'trading company', 'trading platform'],
        'shopping-retail' => ['retail', 'retail services', 'shop', 'shops', 'store', 'stores', 'stationery', 'books', 'bookshop', 'office supplies', 'bicycles', 'bicycle'],
        'automotive' => ['automotive', 'automobile', 'vehicle', 'vehicles', 'auto'],
    ];

    /** @var array<string, int>|null slug => category id */
    private ?array $ids = null;

    /**
     * @param  list<string>  $categories  source category labels
     * @param  list<string>  $tags
     * @return list<int> category ids, most relevant first (never empty)
     */
    public function match(array $categories, array $tags = [], ?string $name = null): array
    {
        $slugs = [];
        foreach ($categories as $label) {
            if (($slug = $this->slugFor($label)) !== null) {
                $slugs[$slug] = true;
            }
        }
        if ($slugs === []) {
            foreach ($tags as $tag) {
                if (($slug = $this->slugFor($tag)) !== null) {
                    $slugs[$slug] = true;
                    break;
                }
            }
        }
        if ($slugs === [] && $name) {
            if (($slug = $this->slugFor($name)) !== null) {
                $slugs[$slug] = true;
            }
        }

        $ids = [];
        foreach (array_keys($slugs) as $slug) {
            if (($id = $this->id($slug)) !== null) {
                $ids[] = $id;
            }
        }

        return $ids ?: array_filter([$this->id(self::FALLBACK)]);
    }

    public function isFallback(int $categoryId): bool
    {
        return $categoryId === $this->id(self::FALLBACK);
    }

    public function slugFor(string $label): ?string
    {
        $text = ' '.trim(preg_replace('/\s+/', ' ', preg_replace('/[^\p{L}\p{N}&\/]+/u', ' ', Str::lower($label)) ?? '') ?? '').' ';
        if (trim($text) === '') {
            return null;
        }
        foreach (self::RULES as $slug => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, ' '.$keyword.' ')) {
                    return $slug;
                }
            }
        }

        return null;
    }

    public function id(string $slug): ?int
    {
        $this->ids ??= Category::query()->pluck('id', 'slug')->all();

        return $this->ids[$slug] ?? null;
    }
}
