<?php

declare(strict_types=1);

namespace App\Support\Places;

use App\Enums\BusinessStatus;
use App\Models\Area;
use App\Models\Business;
use App\Models\BusinessIdentifier;
use App\Models\Category;
use App\Models\Municipality;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BusinessManager;
use App\Services\SlugGenerator;
use App\Support\Directory;
use App\Support\Enrichment\Enricher;
use App\Support\Enrichment\EnrichmentException;
use App\Support\Enrichment\GooglePlaces;
use App\Support\Enrichment\PlaceMatcher;
use App\Support\Import\BusinessImporter;
use App\Support\Import\CategoryMatcher;
use App\Support\Import\LocationMatcher;
use App\Support\Import\Normalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Google Maps finder: searches an area of Qatar for a kind of business,
 * tells the admin which places are already listed (and how sure that is),
 * and adds the new ones after the admin has looked at them.
 *
 * Search results are shown live and never stored. A listing keeps what the
 * admin confirmed when adding it, plus the Google Place ID that links them.
 */
final class PlaceFinder
{
    /** Search radius around a point on the map, in km. */
    public const RADII = [1, 2, 3, 5, 10];

    /** From this score a listing is very probably the same place. */
    public const LIKELY = 70;

    public const PLACE_ID = '/^[A-Za-z0-9_\-]{10,255}$/';

    /** Google types that say nothing about the kind of business. */
    private const GENERIC_TYPES = ['point_of_interest', 'establishment', 'store', 'food', 'health', 'finance', 'premise', 'locality', 'political'];

    /** @var Collection<int, Municipality>|null municipality centres, for places whose address names none */
    private ?Collection $municipalities = null;

    public function __construct(
        private readonly GooglePlaces $google,
        private readonly PlaceMatcher $matcher,
        private readonly CategoryMatcher $categories,
        private readonly LocationMatcher $locations,
        private readonly BusinessImporter $importer,
        private readonly BusinessManager $manager,
        private readonly SlugGenerator $slugs,
        private readonly AuditLogger $audit,
    ) {}

    /** The text sent to Google: "restaurants in Al Wakrah, Qatar" (a point on the map limits the area by itself). */
    public static function query(string $keyword, ?Municipality $municipality, ?Area $area, bool $point = false): string
    {
        $keyword = trim(preg_replace('/\s+/u', ' ', $keyword) ?? '');
        if ($point) {
            return $keyword;
        }
        $place = $area !== null ? $area->name_en.', '.$area->municipality?->name_en : $municipality?->name_en;

        return $keyword.' in '.($place ? $place.', ' : '').'Qatar';
    }

    /**
     * One page of results, each with its match against the directory.
     *
     * @return array{rows: list<array<string,mixed>>, next: ?string}
     *
     * @throws EnrichmentException
     */
    public function search(string $query, ?array $rectangle = null, ?string $pageToken = null): array
    {
        $page = $this->google->searchArea($query, $rectangle, $pageToken);

        return ['rows' => $this->rows($page['places']), 'next' => $page['next']];
    }

    /**
     * @param  list<array<string,mixed>>  $places  Google places (search fields)
     * @return list<array<string,mixed>>
     */
    public function rows(array $places): array
    {
        $places = array_values(array_filter($places, fn ($p) => is_array($p) && preg_match(self::PLACE_ID, (string) ($p['id'] ?? ''))));
        $matches = $this->match($places);

        return array_map(fn (array $place) => $this->row($place, $matches[$place['id']] ?? null), $places);
    }

    /**
     * Adds a place to the directory. Values the admin typed win; anything left out comes from Google.
     *
     * @param  array<string, mixed>  $input  name_en, name_ar, category_id, municipality_id, area_id, phone, website, address_en (present = admin's value, even empty), hours, publish, force
     *
     * @throws ValidationException|DuplicateListing|EnrichmentException
     */
    public function add(string $placeId, array $input, User $by): Business
    {
        if (! preg_match(self::PLACE_ID, $placeId)) {
            throw ValidationException::withMessages(['place_id' => __('places.errors.invalid_place')]);
        }
        $this->ensureNotLinked($placeId);

        $place = $this->google->details($placeId, 'en', GooglePlaces::PLACE_FIELDS);
        try {
            $arabic = $this->google->details($placeId, 'ar', ['displayName', 'formattedAddress']);
        } catch (EnrichmentException) {
            $arabic = [];
        }

        $values = $this->values($place, $arabic, $input);
        if (empty($input['force'])) {
            [$duplicate] = $this->importer->findDuplicate([
                'name_en' => $values['name_en'],
                'name_ar' => $values['name_ar'] ?: null,
                'phone' => Normalizer::phone($values['phone']),
                'website' => $values['website'],
                'latitude' => $values['latitude'],
                'longitude' => $values['longitude'],
                'city' => null,
                'address' => $values['address_en'],
            ]);
            if ($duplicate !== null && ! $duplicate->trashed()) {
                throw new DuplicateListing($duplicate);
            }
        }

        $hours = ! array_key_exists('hours', $input) || ! empty($input['hours'])
            ? Enricher::hoursFromGoogle((array) data_get($place, 'regularOpeningHours.periods', [])) : [];
        $publish = ! empty($input['publish']);

        $business = DB::transaction(function () use ($placeId, $place, $values, $hours, $publish, $by) {
            // Checked again under the transaction: two admins may add the same place at once.
            $this->ensureNotLinked($placeId);

            $business = new Business;
            $business->forceFill($values + [
                'google_place_id' => $placeId,
                'google_maps_url' => mb_substr(safe_external_url((string) data_get($place, 'googleMapsUri')) ?? GooglePlaces::mapsUrl($placeId), 0, 500),
                'geo_precision' => $values['latitude'] !== null ? 'exact' : null,
                'website_status' => $values['website'] !== null ? 'found' : null,
                'is_demo' => false,
                'submitted_at' => now(),
            ]);
            $business->slug = $this->slugs->unique(Business::class, $values['name_en']);
            $business->status = $publish ? BusinessStatus::Approved : BusinessStatus::Draft;
            if ($publish) {
                $business->approved_at = now();
                $business->published_at = now();
            }
            $business->save();

            if ($hours !== []) {
                $this->manager->syncHours($business, $hours);
            }

            $this->audit->log('places.added', $business, [], ['name_en' => $business->name_en, 'status' => $business->status->value], ['place_id' => $placeId], $by->id);

            return $business;
        });

        Directory::flushCounts();
        if ($business->latitude !== null) {
            Cache::forget('map.points.ar');
            Cache::forget('map.points.en');
        }

        return $business;
    }

    /** What the finder shows about a listing. @return array<string, mixed> */
    public static function present(Business $business): array
    {
        return [
            'id' => $business->id,
            'name' => $business->t('name'),
            'name_en' => $business->name_en,
            'name_ar' => $business->name_ar,
            'category' => $business->category?->t('name'),
            'location' => $business->area?->t('name') ?? $business->municipality?->t('name'),
            'status' => $business->status->value,
            'status_label' => $business->status->label(),
            'linked' => $business->google_place_id !== null,
            'edit_url' => route('admin.businesses.edit', $business->id),
            'view_url' => $business->isPublished() ? route('business.show', $business) : null,
        ];
    }

    private function ensureNotLinked(string $placeId): void
    {
        $taken = Business::query()->withTrashed()->where('google_place_id', $placeId)->first(['id', 'name_en', 'deleted_at']);
        if ($taken !== null) {
            throw ValidationException::withMessages(['place_id' => __('places.errors.already_listed', ['name' => $taken->name_en])]);
        }
    }

    /** @return array<string, mixed> listing columns */
    private function values(array $place, array $arabic, array $input): array
    {
        $fail = fn (string $field, string $key = 'invalid') => throw ValidationException::withMessages([$field => __('places.errors.'.$key)]);
        $has = fn (string $key) => array_key_exists($key, $input);
        $text = fn (mixed $v, int $limit) => mb_substr(trim(strip_tags(is_scalar($v) ? (string) $v : '')), 0, $limit);

        $nameEn = $has('name_en') ? $text($input['name_en'], 160) : $text(data_get($place, 'displayName.text'), 160);
        if ($nameEn === '') {
            $fail('name_en', 'name_required');
        }

        $googleAr = $text(data_get($arabic, 'displayName.text'), 160);
        $nameAr = $has('name_ar') && $text($input['name_ar'], 160) !== '' ? $text($input['name_ar'], 160) : (Normalizer::hasArabic($googleAr) ? $googleAr : '');
        if ($nameAr !== '' && ! Normalizer::hasArabic($nameAr)) {
            $fail('name_ar', 'arabic');
        }

        $suggested = $this->suggestions($place);
        $categoryId = $has('category_id') && $input['category_id'] !== null && $input['category_id'] !== ''
            ? (Category::query()->active()->whereKey((int) $input['category_id'])->value('id') ?? $fail('category_id'))
            : $suggested['category_id'];

        $areaId = $has('area_id') ? ($input['area_id'] !== null && $input['area_id'] !== '' ? (int) $input['area_id'] : null) : $suggested['area_id'];
        $municipalityId = $has('municipality_id') ? ($input['municipality_id'] !== null && $input['municipality_id'] !== '' ? (int) $input['municipality_id'] : null) : $suggested['municipality_id'];
        if ($areaId !== null) {
            $area = Area::query()->find($areaId) ?? $fail('area_id');
            $municipalityId = $area->municipality_id;
        }
        if ($municipalityId !== null && ! Municipality::query()->whereKey($municipalityId)->exists()) {
            $fail('municipality_id');
        }

        $googlePhone = Normalizer::phone((string) (data_get($place, 'internationalPhoneNumber') ?: data_get($place, 'nationalPhoneNumber')));
        $phone = $has('phone') ? $text($input['phone'], 40) : ($googlePhone !== null ? Normalizer::displayPhone($googlePhone) : '');
        if ($phone !== '') {
            $phone = ($p = Normalizer::phone($phone)) !== null ? Normalizer::displayPhone($p) : $fail('phone');
        }

        $website = $has('website') ? $text($input['website'], 300) : (string) data_get($place, 'websiteUri');
        if ($website !== '') {
            $website = safe_external_url(Normalizer::website($website)) ?? ($has('website') ? $fail('website') : '');
        }

        $addressEn = $has('address_en') ? $text($input['address_en'], 300) : $text(data_get($place, 'formattedAddress'), 300);
        $addressAr = $text(data_get($arabic, 'formattedAddress'), 300);

        $lat = data_get($place, 'location.latitude');
        $lng = data_get($place, 'location.longitude');
        $inQatar = is_numeric($lat) && is_numeric($lng) && $lat >= 24.4 && $lat <= 26.3 && $lng >= 50.7 && $lng <= 51.8;

        return [
            'name_en' => $nameEn,
            'name_ar' => $nameAr,
            'category_id' => $categoryId,
            'municipality_id' => $municipalityId,
            'area_id' => $areaId,
            'phone' => $phone ?: null,
            'website' => $website ?: null,
            'address_en' => $addressEn ?: null,
            'address_ar' => Normalizer::hasArabic($addressAr) ? $addressAr : null,
            'latitude' => $inQatar ? round((float) $lat, 7) : null,
            'longitude' => $inQatar ? round((float) $lng, 7) : null,
        ];
    }

    /** Category and location the finder proposes for a place. @return array{category_id: int, fallback: bool, municipality_id: ?int, area_id: ?int} */
    public function suggestions(array $place): array
    {
        $types = array_map(fn ($t) => str_replace('_', ' ', (string) $t), array_diff((array) ($place['types'] ?? []), self::GENERIC_TYPES));
        $labels = array_values(array_filter([(string) data_get($place, 'primaryTypeDisplayName.text'), ...$types]));
        $categoryId = $this->categories->match($labels, [], (string) data_get($place, 'displayName.text'))[0];

        $location = $this->locations->match(null, (string) data_get($place, 'formattedAddress'));
        if ($location['municipality_id'] === null && is_numeric(data_get($place, 'location.latitude'))) {
            $location['municipality_id'] = $this->nearestMunicipality((float) data_get($place, 'location.latitude'), (float) data_get($place, 'location.longitude'));
        }

        return ['category_id' => $categoryId, 'fallback' => $this->categories->isFallback($categoryId)] + $location;
    }

    private function nearestMunicipality(float $lat, float $lng): ?int
    {
        $best = null;
        $distance = INF;
        foreach ($this->municipalities() as $m) {
            $km = PlaceMatcher::distanceKm($lat, $lng, (float) $m->latitude, (float) $m->longitude);
            if ($km < $distance) {
                [$best, $distance] = [$m->id, $km];
            }
        }

        // Further than this from every municipality centre is too far to guess.
        return $distance <= 25 ? $best : null;
    }

    /** @return Collection<int, Municipality> */
    private function municipalities(): Collection
    {
        return $this->municipalities ??= Municipality::query()->whereNotNull('latitude')->whereNotNull('longitude')->get(['id', 'latitude', 'longitude']);
    }

    /** @return array<string, mixed> */
    private function row(array $place, ?array $match): array
    {
        $id = (string) $place['id'];
        $phone = Normalizer::phone((string) (data_get($place, 'internationalPhoneNumber') ?: data_get($place, 'nationalPhoneNumber')));
        $lat = data_get($place, 'location.latitude');
        $lng = data_get($place, 'location.longitude');
        $suggested = $this->suggestions($place);

        return [
            'place_id' => $id,
            'name' => (string) data_get($place, 'displayName.text'),
            'address' => (string) data_get($place, 'formattedAddress'),
            'type' => data_get($place, 'primaryTypeDisplayName.text'),
            'lat' => is_numeric($lat) ? (float) $lat : null,
            'lng' => is_numeric($lng) ? (float) $lng : null,
            'phone' => $phone !== null ? Normalizer::displayPhone($phone) : null,
            'website' => safe_external_url(Normalizer::website((string) data_get($place, 'websiteUri'))),
            'maps_url' => safe_external_url((string) data_get($place, 'googleMapsUri')) ?? GooglePlaces::mapsUrl($id),
            'rating' => is_numeric(data_get($place, 'rating')) ? round((float) data_get($place, 'rating'), 1) : null,
            'rating_count' => (int) data_get($place, 'userRatingCount', 0),
            'business_status' => data_get($place, 'businessStatus'),
            'category_id' => $suggested['category_id'],
            'category_fallback' => $suggested['fallback'],
            'municipality_id' => $suggested['municipality_id'],
            'area_id' => $suggested['area_id'],
            'state' => $match['state'] ?? 'new',
            'score' => $match['score'] ?? null,
            'reasons' => $match['reasons'] ?? [],
            'business' => isset($match['business']) ? self::present($match['business']) : null,
        ];
    }

    /**
     * Finds the listing each place probably is: already linked, or the best candidate
     * sharing its phone number, website, name, or a pin within a few hundred metres.
     *
     * @param  list<array<string,mixed>>  $places
     * @return array<string, array{state: string, score: int, reasons: list<string>, business: Business}>
     */
    private function match(array $places): array
    {
        if ($places === []) {
            return [];
        }
        $relations = ['category', 'municipality', 'area'];
        $result = [];

        $linked = Business::query()->whereIn('google_place_id', array_column($places, 'id'))->with($relations)->get();
        foreach ($linked as $business) {
            $result[$business->google_place_id] = ['state' => 'linked', 'score' => 100, 'reasons' => ['place_linked'], 'business' => $business];
        }
        $rest = array_values(array_filter($places, fn ($p) => ! isset($result[$p['id']])));
        if ($rest === []) {
            return $result;
        }

        // Candidate listings per place.
        $wanted = [];
        $keys = [];
        foreach ($rest as $place) {
            $id = $place['id'];
            if (($phone = Normalizer::phone((string) (data_get($place, 'internationalPhoneNumber') ?: data_get($place, 'nationalPhoneNumber')))) !== null) {
                $wanted['phone'][$phone][] = $id;
            }
            if (($domain = Normalizer::domain((string) data_get($place, 'websiteUri'))) !== null) {
                $wanted['domain'][$domain][] = $id;
            }
            if (($key = Normalizer::nameKey((string) data_get($place, 'displayName.text'))) !== null) {
                $keys[$key][] = $id;
            }
        }

        $candidates = [];
        if ($wanted !== []) {
            $identifiers = BusinessIdentifier::query()
                ->where(function ($q) use ($wanted) {
                    foreach ($wanted as $type => $values) {
                        $q->orWhere(fn ($w) => $w->where('type', $type)->whereIn('value', array_map('strval', array_keys($values))));
                    }
                })
                ->limit(500)->get(['business_id', 'type', 'value']);
            foreach ($identifiers as $identifier) {
                foreach ($wanted[$identifier->type][$identifier->value] ?? [] as $placeId) {
                    $candidates[$placeId][$identifier->business_id] = true;
                }
            }
        }
        if ($keys !== []) {
            foreach (Business::query()->whereIn('name_key', array_map('strval', array_keys($keys)))->limit(500)->get(['id', 'name_key']) as $b) {
                foreach ($keys[$b->name_key] ?? [] as $placeId) {
                    $candidates[$placeId][$b->id] = true;
                }
            }
        }

        // Exact pins close to a place (a same-name check decides later).
        $pinned = array_values(array_filter($rest, fn ($p) => is_numeric(data_get($p, 'location.latitude')) && is_numeric(data_get($p, 'location.longitude'))));
        $nearby = collect();
        if ($pinned !== []) {
            $d = 0.003; // about 300 m
            $nearby = Business::query()->where('geo_precision', 'exact')
                ->where(function ($q) use ($pinned, $d) {
                    foreach ($pinned as $p) {
                        $lat = (float) data_get($p, 'location.latitude');
                        $lng = (float) data_get($p, 'location.longitude');
                        $q->orWhere(fn ($w) => $w->whereBetween('latitude', [$lat - $d, $lat + $d])->whereBetween('longitude', [$lng - $d, $lng + $d]));
                    }
                })
                ->limit(600)->get(['id', 'name_en', 'name_ar', 'latitude', 'longitude']);
            foreach ($pinned as $p) {
                $name = (string) data_get($p, 'displayName.text');
                foreach ($nearby as $b) {
                    if (PlaceMatcher::distanceKm((float) data_get($p, 'location.latitude'), (float) data_get($p, 'location.longitude'), (float) $b->latitude, (float) $b->longitude) <= 0.3
                        && max(Normalizer::nameSimilarity($name, $b->name_en), Normalizer::nameSimilarity($name, $b->name_ar)) >= 0.5) {
                        $candidates[$p['id']][$b->id] = true;
                    }
                }
            }
        }

        $ids = array_keys(array_replace(...array_values($candidates ?: [[]])));
        if ($ids === []) {
            return $result;
        }
        $businesses = Business::query()->whereIn('id', $ids)->with($relations)->get()->keyBy('id');

        foreach ($rest as $place) {
            $best = null;
            foreach (array_keys($candidates[$place['id']] ?? []) as $businessId) {
                $business = $businesses->get($businessId);
                // A listing already linked to another Google place is a different place (or a branch).
                if ($business === null || ($business->google_place_id !== null && $business->google_place_id !== $place['id'])) {
                    continue;
                }
                $scored = $this->matcher->score($business, $place);
                if ($best === null || $scored['score'] > $best['score']) {
                    $best = $scored + ['business' => $business];
                }
            }
            if ($best !== null && $best['score'] >= PlaceMatcher::MIN_SCORE) {
                $result[$place['id']] = ['state' => $best['score'] >= self::LIKELY ? 'likely' : 'possible'] + $best;
            }
        }

        return $result;
    }
}
