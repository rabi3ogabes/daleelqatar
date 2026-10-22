<?php

declare(strict_types=1);

namespace App\Support\Enrichment;

use App\Models\Business;
use App\Models\BusinessSource;
use App\Models\BusinessSuggestion;
use App\Models\Category;
use App\Models\ScrapeSource;
use App\Support\Deadline;
use App\Support\Discovery\DiscoveryException;
use App\Support\Import\BusinessImporter;
use App\Support\Import\CategoryMatcher;
use App\Support\Import\Normalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Collects improvements for a listing from Google Maps, the company website
 * (found from the listing or its business e-mail domain) and the AI writer,
 * and stores them as one suggestion for an admin to review. Only empty
 * fields are proposed; nothing is written to the listing here.
 */
final class Enricher
{
    public function __construct(
        private readonly GooglePlaces $google,
        private readonly PlaceMatcher $matcher,
        private readonly WebsiteReader $reader,
        private readonly AiWriter $ai,
        private readonly BusinessImporter $importer,
        private readonly CategoryMatcher $categories,
    ) {}

    /**
     * @param  array{google?: bool, website?: bool, ai?: bool}  $options
     */
    public function run(Business $business, array $options = [], ?string $placeId = null): BusinessSuggestion
    {
        $options += ['google' => true, 'website' => true, 'ai' => true];
        $business->loadMissing(['category', 'municipality', 'area', 'openingHours', 'services', 'socialLinks']);

        $fields = [];
        $notes = [];
        $sources = [];
        $match = null;
        $place = null;
        $websiteStatus = $business->website_status;

        // 1. Google Maps
        if ($options['google'] && GooglePlaces::enabled()) {
            try {
                [$place, $match] = $this->findPlace($business, $placeId);
                if ($place !== null) {
                    $sources[] = 'google';
                    $this->fromGoogle($business, $place, $match, $fields);
                } else {
                    $notes['google'] = 'no_match';
                }
            } catch (EnrichmentException $e) {
                $notes['google'] = $e->getMessage();
            }
        } elseif ($options['google']) {
            $notes['google'] = 'google_not_configured';
        }

        // 2. Company website (listing → business e-mail domain → Google)
        if ($options['website'] && ! Deadline::has(15)) {
            $notes['website'] = 'out_of_time';
        } elseif ($options['website']) {
            $candidates = $this->reader->candidates($business, $place['websiteUri'] ?? null);
            if ($candidates === []) {
                $websiteStatus = 'none';
                $notes['website'] = 'no_website';
            } else {
                $websiteStatus = 'unreachable';
                $notes['website'] = 'unreachable';
                foreach (array_slice($candidates, 0, 2) as $candidate) {
                    try {
                        $site = $this->reader->read($candidate['url']);
                    } catch (DiscoveryException $e) {
                        if ($e->getMessage() === 'out_of_time') {
                            // Ran out of time: the website status stays as it was, the next run tries again.
                            $websiteStatus = $business->website_status;
                            $notes['website'] = 'out_of_time';
                            break;
                        }
                        $site = null;
                    } catch (Throwable $e) {
                        report($e);
                        $site = null;
                    }
                    if ($site === null) {
                        continue;
                    }
                    $sources[] = 'website';
                    unset($notes['website']);
                    $websiteStatus = $candidate['source'] === 'listing' ? 'found' : 'inferred';
                    $this->fromWebsite($business, $site, $candidate, $fields, $notes, (bool) $options['ai']);
                    break;
                }
            }
        }

        // 2b. No description from the website: what other directories said (kept privately when
        //     their listing was imported) is rewritten in our own words — never published as is.
        if ($options['ai'] && ! isset($fields['description_en']) && (blank($business->description_en) || blank($business->description_ar))) {
            $this->fromSnapshots($business, $fields, $notes, $sources);
        }

        // 3. A mobile number is often on WhatsApp — offered, clearly marked as unconfirmed.
        if (! isset($fields['whatsapp']) && blank($business->whatsapp)) {
            $phone = Normalizer::phone((string) ($fields['phone']['value'] ?? $business->phone));
            if ($phone !== null && Normalizer::isQatarMobile($phone)) {
                $fields['whatsapp'] = $this->field(Normalizer::displayPhone($phone), null, 'inferred', 'low', 'mobile_maybe_whatsapp');
            }
        }

        // 4. Arabic name: Google first, otherwise a suggested translation.
        if (! isset($fields['name_ar']) && ! Normalizer::hasArabic((string) $business->name_ar) && $options['ai'] && $this->ai->available()) {
            try {
                $names = $this->ai->arabicNames([$this->nameRow($business)]);
                if (isset($names[$business->id])) {
                    $fields['name_ar'] = $this->field($names[$business->id], $business->name_ar, 'ai', 'medium', 'ai_translation');
                    $sources[] = 'ai';
                }
            } catch (EnrichmentException $e) {
                $notes['ai'] = $e->getMessage();
            }
        }

        return $this->store($business, $fields, $match, array_values(array_unique($sources)), $notes, $websiteStatus);
    }

    private function fromSnapshots(Business $business, array &$fields, array &$notes, array &$sources): void
    {
        $snapshots = BusinessSource::query()->where('business_id', $business->id)->whereNotNull('details')->latest('last_seen_at')->limit(3)->get(['source', 'url', 'details']);
        $pages = [];
        $site = null;
        foreach ($snapshots as $snapshot) {
            $details = (array) $snapshot->details;
            if (mb_strlen((string) ($details['description'] ?? '')) < 40) {
                continue;
            }
            $pages[(string) ($snapshot->url ?: $snapshot->source)] = trim((! empty($details['categories']) ? 'Category: '.implode(', ', (array) $details['categories'])."\n" : '').$details['description']);
            $site ??= Normalizer::domain((string) $snapshot->url) ?? $snapshot->source;
        }
        if ($pages === [] || ! $this->ai->available() || ! Deadline::has(20)) {
            return;
        }

        try {
            $profile = $this->ai->profileFromWebsite($business->name_en, $business->category?->name_en, $pages, 'directory');
        } catch (EnrichmentException $e) {
            $notes['ai'] = $e->getMessage();

            return;
        }
        if (! $profile['found']) {
            return;
        }
        foreach (['description_en', 'description_ar', 'tagline_en', 'tagline_ar'] as $key) {
            if (blank($business->{$key}) && ! isset($fields[$key]) && filled($profile[$key])) {
                $fields[$key] = $this->field($profile[$key], null, 'ai', 'medium', 'written_from_directory', ['site' => $site]);
            }
        }
        if ($business->services->isEmpty() && ! isset($fields['services']) && $profile['services'] !== []) {
            $fields['services'] = $this->field($profile['services'], null, 'ai', 'medium', 'written_from_directory', ['site' => $site]);
        }
        $sources[] = 'ai';
    }

    /**
     * What another directory knows about this listing (a page found by DirectoryLookup), as a
     * suggestion for review: its facts for the fields still empty, and — never its wording —
     * a description the AI writes from those facts. Joins a suggestion already waiting for review.
     *
     * @param  array{url: string, title: string, score: float, record: array}  $match
     */
    public function fromDirectory(Business $business, ScrapeSource $source, array $match, bool $useAi = true): BusinessSuggestion
    {
        $business->loadMissing(['category', 'services', 'socialLinks']);
        $record = $match['record'];
        $confidence = $match['score'] >= 0.9 ? 'high' : 'medium';
        $note = ['site' => $source->name];
        $fields = [];
        $notes = [];

        $phones = array_values(array_unique(array_filter(array_map(fn ($p) => Normalizer::phone((string) $p), [$record['phone'] ?? null, ...($record['extra_phones'] ?? [])]))));
        $known = array_filter(array_map(fn ($p) => Normalizer::phone((string) $p), [$business->phone, $business->whatsapp, ...(array) $business->extra_phones]));
        if (blank($business->phone) && $phones !== []) {
            $fields['phone'] = $this->field(Normalizer::displayPhone(array_shift($phones)), null, 'directory', $confidence, 'from_directory', $note);
        }
        $morePhones = array_values(array_diff($phones, $known, [Normalizer::phone((string) ($fields['phone']['value'] ?? ''))]));
        if ($morePhones !== []) {
            $fields['extra_phones'] = $this->field(array_map(fn ($p) => Normalizer::displayPhone($p), array_slice($morePhones, 0, 5)), $business->extra_phones ? implode(', ', (array) $business->extra_phones) : null, 'directory', $confidence, 'from_directory', $note);
        }
        if (blank($business->whatsapp) && ($wa = Normalizer::phone((string) ($record['whatsapp'] ?? ''))) !== null) {
            $fields['whatsapp'] = $this->field(Normalizer::displayPhone($wa), null, 'directory', $confidence, 'from_directory', $note);
        }

        $emails = array_values(array_unique(array_map('strtolower', array_filter([$record['email'] ?? null, ...($record['extra_emails'] ?? [])]))));
        $knownMail = array_map('strtolower', array_filter([$business->email, ...(array) $business->extra_emails]));
        if (blank($business->email) && $emails !== []) {
            $fields['email'] = $this->field(array_shift($emails), null, 'directory', $confidence, 'from_directory', $note);
        }
        $moreMail = array_values(array_diff($emails, $knownMail));
        if ($moreMail !== []) {
            $fields['extra_emails'] = $this->field(array_slice($moreMail, 0, 5), $business->extra_emails ? implode(', ', (array) $business->extra_emails) : null, 'directory', $confidence, 'from_directory', $note);
        }

        if (blank($business->website) && ($site = Normalizer::website((string) ($record['website'] ?? ''))) !== null && Normalizer::domain($site) !== null) {
            $fields['website'] = $this->field($site, null, 'directory', $confidence, 'from_directory', $note);
        }
        if (blank($business->address_en) && filled($record['address'] ?? null)) {
            $fields['address_en'] = $this->field(mb_substr((string) $record['address'], 0, 300), null, 'directory', $confidence, 'from_directory', $note);
        }
        // Placeholder pins (the same point for every company) were already dropped by the reader.
        if (($record['latitude'] ?? null) !== null && ($business->latitude === null || $business->geo_precision !== 'exact')) {
            $fields['location'] = $this->field(['lat' => round((float) $record['latitude'], 7), 'lng' => round((float) $record['longitude'], 7)],
                $business->latitude !== null ? $business->latitude.', '.$business->longitude : null, 'directory', 'medium', 'from_directory', $note);
        }
        if ($business->socialLinks->isEmpty() && ($record['social'] ?? []) !== []) {
            $fields['social'] = $this->field($record['social'], null, 'directory', $confidence, 'from_directory', $note);
        }
        if (blank($business->logo_path) && filled($record['logo_url'] ?? null)) {
            $fields['logo'] = $this->field($record['logo_url'], null, 'directory', 'medium', 'logo_from_directory', $note);
        }

        // Their category, when ours is only the catch-all.
        $labels = array_values(array_filter((array) ($record['categories'] ?? [])));
        if ($labels !== [] && $this->categories->isFallback((int) $business->category_id)) {
            $ids = $this->categories->match($labels, [], $business->name_en, ['name_ar' => $business->name_ar, 'description' => $record['description'] ?? null]);
            if (! $this->categories->isFallback($ids[0]) && ($category = Category::query()->find($ids[0])) !== null) {
                $fields['category'] = $this->field(['id' => $category->id, 'label' => $category->t('name')], $business->category?->t('name'), 'directory', 'medium', 'category_from_directory', $note + ['label' => implode(' · ', $labels)]);
            }
        }

        // Their text only informs a description written in our own words.
        $text = trim(implode("\n", array_filter([
            $labels !== [] ? 'Category: '.implode(', ', $labels) : null,
            $record['description'] ?? null,
        ])));
        $needsText = blank($business->description_en) || blank($business->description_ar) || blank($business->tagline_en) || $business->services->isEmpty();
        if ($needsText && mb_strlen((string) ($record['description'] ?? '')) >= 40) {
            if (! $useAi || ! $this->ai->available()) {
                $notes['ai'] = 'ai_not_configured';
            } else {
                try {
                    $profile = $this->ai->profileFromWebsite($business->name_en, $business->category?->name_en, [$match['url'] => $text], 'directory');
                    if ($profile['found']) {
                        foreach (['description_en', 'description_ar', 'tagline_en', 'tagline_ar'] as $key) {
                            if (blank($business->{$key}) && filled($profile[$key])) {
                                $fields[$key] = $this->field($profile[$key], null, 'ai', 'medium', 'written_from_directory', $note);
                            }
                        }
                        if ($business->services->isEmpty() && $profile['services'] !== []) {
                            $fields['services'] = $this->field($profile['services'], null, 'ai', 'medium', 'written_from_directory', $note);
                        }
                    }
                } catch (EnrichmentException $e) {
                    $notes['ai'] = $e->getMessage();
                }
            }
        }

        $notes['directory'] = array_filter([
            'source' => $source->name,
            'source_id' => $source->id,
            'url' => $match['url'],
            'title' => $match['title'],
            'score' => $match['score'],
            'chosen' => ! empty($match['chosen']),
            'categories' => $labels,
            'image' => $record['image_url'] ?? null,
            'logo' => $record['logo_url'] ?? null,
            'phones' => array_values(array_filter([$record['phone'] ?? null, ...($record['extra_phones'] ?? [])])),
            'emails' => array_values(array_filter([$record['email'] ?? null, ...($record['extra_emails'] ?? [])])),
            'address' => $record['address'] ?? $record['city'] ?? null,
        ], fn ($v) => $v !== null && $v !== [] && $v !== '');

        $this->importer->remember($business->id, $source->sourceKey(), $match['url'], $record);

        return $this->store($business, $fields, null, array_values(array_unique(['directory', ...(isset($fields['description_en']) ? ['ai'] : [])])), $notes, $business->website_status, merge: true);
    }

    /**
     * Fast path for many listings at once: one AI request suggests Arabic names for up to 40 businesses.
     *
     * @param  Collection<int, Business>  $businesses
     */
    public function translateNames(Collection $businesses): int
    {
        $todo = $businesses->filter(fn (Business $b) => ! Normalizer::hasArabic((string) $b->name_ar))->values();
        if ($todo->isEmpty() || ! $this->ai->available()) {
            return 0;
        }
        $todo->loadMissing(['category', 'municipality', 'area']);
        $names = $this->ai->arabicNames($todo->map(fn (Business $b) => $this->nameRow($b))->all());

        $created = 0;
        foreach ($todo as $business) {
            if (! isset($names[$business->id])) {
                continue;
            }
            $field = $this->field($names[$business->id], $business->name_ar, 'ai', 'medium', 'ai_translation');
            DB::transaction(function () use ($business, $field, &$created) {
                $pending = BusinessSuggestion::query()->where('business_id', $business->id)->pending()->lockForUpdate()->latest('id')->first();
                if ($pending !== null) {
                    if (! isset($pending->fields['name_ar'])) {
                        $pending->fields = ['name_ar' => $field] + $pending->fields;
                        $pending->sources = array_values(array_unique([...(array) $pending->sources, 'ai']));
                        $pending->save();
                        $created++;
                    }

                    return;
                }
                BusinessSuggestion::query()->create([
                    'business_id' => $business->id, 'status' => 'pending', 'fields' => ['name_ar' => $field], 'sources' => ['ai'],
                ]);
                $created++;
            });
        }

        return $created;
    }

    /** @return array{0: ?array, 1: ?array} the chosen place and the match summary shown to the admin */
    private function findPlace(Business $business, ?string $placeId): array
    {
        if ($placeId !== null) {
            $place = $this->google->details($placeId, 'en', GooglePlaces::PLACE_FIELDS);
            $scored = $this->matcher->score($business, $place);

            // The admin picked this place: it counts as a confident match.
            return [$place, $this->summary($place, max($scored['score'], 90), [...$scored['reasons'], 'chosen_by_admin'], [])];
        }

        $location = $business->area?->name_en ?? $business->municipality?->name_en;
        $query = trim($business->name_en.' '.($location ?? '').' Qatar');
        $ranked = $this->matcher->rank($business, $this->google->search($query, 'en', 5));
        if ($ranked === []) {
            return [null, null];
        }

        $others = array_map(fn ($r) => [
            'place_id' => (string) data_get($r['place'], 'id'),
            'name' => (string) data_get($r['place'], 'displayName.text'),
            'address' => (string) data_get($r['place'], 'formattedAddress'),
            'score' => $r['score'],
        ], array_slice($ranked, 1, 4));

        $best = $ranked[0];
        if ($best['score'] < PlaceMatcher::MIN_SCORE) {
            return [null, $this->summary($best['place'], $best['score'], $best['reasons'], $others) + ['rejected' => true]];
        }

        return [$best['place'], $this->summary($best['place'], $best['score'], $best['reasons'], $others)];
    }

    private function summary(array $place, int $score, array $reasons, array $others): array
    {
        $id = (string) data_get($place, 'id');

        return [
            'place_id' => $id,
            'name' => (string) data_get($place, 'displayName.text'),
            'address' => (string) data_get($place, 'formattedAddress'),
            'type' => data_get($place, 'primaryTypeDisplayName.text'),
            'maps_url' => (string) (data_get($place, 'googleMapsUri') ?: GooglePlaces::mapsUrl($id)),
            'status' => data_get($place, 'businessStatus'),
            'rating' => data_get($place, 'rating'),
            'rating_count' => data_get($place, 'userRatingCount'),
            'score' => $score,
            'reasons' => $reasons,
            'candidates' => $others,
        ];
    }

    private function fromGoogle(Business $business, array $place, array $match, array &$fields): void
    {
        $confidence = $match['score'] >= 80 ? 'high' : 'medium';
        $id = (string) data_get($place, 'id');

        if ($business->google_place_id !== $id) {
            $fields['google_place'] = $this->field(['place_id' => $id, 'maps_url' => $match['maps_url']], $business->google_maps_url, 'google', $confidence, 'google_listing');
        }

        // Arabic name and address as Google shows them to Arabic-speaking users.
        if (! Normalizer::hasArabic((string) $business->name_ar) || blank($business->address_ar)) {
            try {
                $arabic = $this->google->details($id, 'ar', ['displayName', 'formattedAddress']);
                $nameAr = trim((string) data_get($arabic, 'displayName.text'));
                if (! Normalizer::hasArabic((string) $business->name_ar) && Normalizer::hasArabic($nameAr)) {
                    $fields['name_ar'] = $this->field($nameAr, $business->name_ar, 'google', $confidence, 'google_arabic_name');
                }
                $addressAr = trim((string) data_get($arabic, 'formattedAddress'));
                if (blank($business->address_ar) && Normalizer::hasArabic($addressAr)) {
                    $fields['address_ar'] = $this->field(mb_substr($addressAr, 0, 300), null, 'google', $confidence);
                }
            } catch (EnrichmentException) {
                // The English details are still useful.
            }
        }

        if (blank($business->address_en) && ($address = trim((string) data_get($place, 'formattedAddress'))) !== '') {
            $fields['address_en'] = $this->field(mb_substr($address, 0, 300), null, 'google', $confidence);
        }

        $phone = Normalizer::phone((string) (data_get($place, 'internationalPhoneNumber') ?: data_get($place, 'nationalPhoneNumber')));
        if ($phone !== null && blank($business->phone)) {
            $fields['phone'] = $this->field(Normalizer::displayPhone($phone), null, 'google', $confidence);
        }

        $website = Normalizer::website((string) data_get($place, 'websiteUri'));
        if ($website !== null && blank($business->website) && Normalizer::domain($website) !== null) {
            $fields['website'] = $this->field($website, null, 'google', $confidence);
        }

        $lat = data_get($place, 'location.latitude');
        $lng = data_get($place, 'location.longitude');
        if (is_numeric($lat) && is_numeric($lng) && ($business->latitude === null || $business->geo_precision !== 'exact')) {
            $fields['location'] = $this->field(['lat' => round((float) $lat, 7), 'lng' => round((float) $lng, 7)],
                $business->latitude !== null ? $business->latitude.', '.$business->longitude : null, 'google', $confidence);
        }

        if ($business->openingHours->isEmpty() && blank($business->opening_hours_text)) {
            $hours = self::hoursFromGoogle((array) data_get($place, 'regularOpeningHours.periods', []));
            if ($hours !== []) {
                $fields['opening_hours'] = $this->field(['days' => $hours, 'lines' => array_values((array) data_get($place, 'regularOpeningHours.weekdayDescriptions', []))], null, 'google', $confidence);
            }
        }
    }

    private function fromWebsite(Business $business, array $site, array $candidate, array &$fields, array &$notes, bool $useAi): void
    {
        if ($candidate['source'] !== 'listing' && blank($business->website)) {
            $fields['website'] = $this->field(rtrim($site['url'], '/'), null, $candidate['source'] === 'email' ? 'email' : 'google',
                $candidate['source'] === 'email' ? 'high' : 'medium', $candidate['source'] === 'email' ? 'website_from_email' : null,
                $candidate['source'] === 'email' ? ['domain' => Normalizer::domain($candidate['url'])] : []);
        }

        if (blank($business->whatsapp) && $site['whatsapp'] !== []) {
            $fields['whatsapp'] = $this->field(Normalizer::displayPhone($site['whatsapp'][0]), null, 'website', 'high', 'whatsapp_link_on_site');
        }
        if (blank($business->phone) && ! isset($fields['phone']) && $site['phones'] !== []) {
            $fields['phone'] = $this->field(Normalizer::displayPhone($site['phones'][0]), null, 'website', 'high');
        }
        if ($business->socialLinks->isEmpty() && $site['social'] !== []) {
            $fields['social'] = $this->field($site['social'], null, 'website', 'high');
        }

        $needsText = blank($business->description_en) || blank($business->description_ar) || blank($business->tagline_en) || $business->services->isEmpty();
        if (! $needsText || $site['pages'] === []) {
            return;
        }
        if (! $useAi || ! $this->ai->available()) {
            $notes['ai'] = $notes['ai'] ?? 'ai_not_configured';

            return;
        }

        try {
            $profile = $this->ai->profileFromWebsite($business->name_en, $business->category?->name_en, $site['pages']);
        } catch (EnrichmentException $e) {
            $notes['ai'] = $e->getMessage();

            return;
        }
        if (! $profile['found']) {
            $notes['website'] = 'site_not_descriptive';

            return;
        }

        foreach (['description_en', 'description_ar', 'tagline_en', 'tagline_ar'] as $key) {
            if (blank($business->{$key}) && filled($profile[$key])) {
                $fields[$key] = $this->field($profile[$key], null, 'website', 'medium', 'written_from_site', ['site' => Normalizer::domain($site['url'])]);
            }
        }
        if ($business->services->isEmpty() && $profile['services'] !== []) {
            $fields['services'] = $this->field($profile['services'], null, 'website', 'medium', 'written_from_site', ['site' => Normalizer::domain($site['url'])]);
        }
    }

    private function store(Business $business, array $fields, ?array $match, array $sources, array $notes, ?string $websiteStatus, bool $merge = false): BusinessSuggestion
    {
        return DB::transaction(function () use ($business, $fields, $match, $sources, $notes, $websiteStatus, $merge) {
            // A partial check (one directory) adds to the suggestion already waiting, instead of replacing it.
            $waiting = $merge ? BusinessSuggestion::query()->where('business_id', $business->id)->pending()->latest('id')->lockForUpdate()->first() : null;
            if ($waiting !== null) {
                $waiting->forceFill([
                    'fields' => self::ordered((array) $waiting->fields + $fields),
                    'sources' => array_values(array_unique([...(array) $waiting->sources, ...$sources])),
                    'notes' => array_merge((array) $waiting->notes, $notes) ?: null,
                ])->save();

                return $waiting;
            }

            BusinessSuggestion::query()->where('business_id', $business->id)->pending()->update(['status' => 'superseded', 'updated_at' => now()]);

            $suggestion = BusinessSuggestion::query()->create([
                'business_id' => $business->id,
                'status' => $fields === [] ? 'empty' : 'pending',
                'match_confidence' => $match['score'] ?? null,
                'match' => $match,
                'fields' => self::ordered($fields),
                'sources' => $sources,
                'notes' => $notes ?: null,
            ]);

            // A check cut short by the request's time limit stays "not enriched", so the next run finishes it.
            // One directory looked up is not a full check: the listing stays due for one.
            $cutShort = array_intersect(array_filter($notes, 'is_string'), ['out_of_time', 'ai_out_of_time', 'google_out_of_time']) !== [];
            if (! $merge) {
                $business->forceFill(['enriched_at' => $cutShort ? $business->enriched_at : now(), 'website_status' => $websiteStatus])->saveQuietly();
            }

            return $suggestion;
        });
    }

    /** Display order on the review screen. */
    public const FIELD_ORDER = [
        'name_ar', 'google_place', 'category', 'logo', 'phone', 'extra_phones', 'whatsapp', 'email', 'extra_emails', 'website', 'social',
        'address_en', 'address_ar', 'location', 'opening_hours', 'tagline_en', 'tagline_ar', 'description_en', 'description_ar', 'services',
    ];

    private static function ordered(array $fields): array
    {
        return array_replace(array_intersect_key(array_flip(self::FIELD_ORDER), $fields), $fields);
    }

    private function field(mixed $value, mixed $current, string $source, string $confidence, ?string $note = null, array $noteParams = []): array
    {
        return array_filter([
            'value' => $value,
            'current' => $current,
            'source' => $source,
            'confidence' => $confidence,
            'note' => $note,
            'note_params' => $noteParams ?: null,
        ], fn ($v) => $v !== null);
    }

    private function nameRow(Business $business): array
    {
        return [
            'id' => $business->id,
            'name' => $business->name_en,
            'category' => $business->category?->name_en,
            'location' => $business->area?->name_en ?? $business->municipality?->name_en,
        ];
    }

    /**
     * Google periods → BusinessManager::syncHours() format (0 = Sunday, like Google).
     *
     * @return array<int, array{closed?: bool, all_day?: bool, periods?: list<array{opens: string, closes: string}>}>
     */
    public static function hoursFromGoogle(array $periods): array
    {
        $time = fn (?array $p) => $p === null ? null : sprintf('%02d:%02d', (int) ($p['hour'] ?? 0), (int) ($p['minute'] ?? 0));

        // Open around the clock: a single period with no closing time.
        if (count($periods) === 1 && ! isset($periods[0]['close']) && (int) data_get($periods[0], 'open.day', 0) === 0) {
            return array_fill(0, 7, ['all_day' => true]);
        }

        $days = [];
        foreach ($periods as $period) {
            if (! isset($period['open']['day'])) {
                continue;
            }
            $day = (int) $period['open']['day'];
            $opens = $time($period['open']);
            $closes = $time($period['close'] ?? null);
            if ($closes === '00:00' || ($closes === null)) {
                $closes = '24:00';
            }
            if ($opens === '00:00' && $closes === '24:00') {
                $days[$day] = ['all_day' => true];

                continue;
            }
            $days[$day]['periods'][] = ['opens' => $opens, 'closes' => $closes];
        }
        for ($d = 0; $d <= 6; $d++) {
            $days[$d] ??= ['closed' => true];
        }
        ksort($days);

        return $days === array_fill(0, 7, ['closed' => true]) ? [] : $days;
    }
}
