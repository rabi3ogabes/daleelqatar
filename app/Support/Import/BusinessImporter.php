<?php

declare(strict_types=1);

namespace App\Support\Import;

use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\BusinessIdentifier;
use App\Models\BusinessSource;
use App\Services\SlugGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adds one normalised record (from a spreadsheet row or a crawled page) to the
 * directory without ever creating a duplicate:
 *
 *  1. the same source record seen before            → update that listing
 *  2. a listing sharing a phone, e-mail or website  → merge into it (when the names agree)
 *  3. a listing with the same name in the same area → merge into it
 *  4. otherwise                                      → create a new listing
 *
 * Merging only fills fields that are still empty, so data entered by an owner
 * or an administrator is never overwritten, and claimed listings are left alone.
 */
final class BusinessImporter
{
    public const CREATED = 'created';

    public const MERGED = 'merged';

    public const SKIPPED = 'skipped';

    public function __construct(
        private readonly SlugGenerator $slugs,
        private readonly CategoryMatcher $categories,
        private readonly LocationMatcher $locations,
    ) {}

    /**
     * @param  array<string, mixed>  $record  output of RecordBuilder
     * @param  array{publish?: bool, update_existing?: bool, default_category_id?: ?int}  $options
     * @return array{status: string, business_id: ?int, message: ?string}
     */
    public function import(array $record, string $sourceKey, array $options = []): array
    {
        $name = $record['name_en'] ?? $record['name_ar'] ?? null;
        if (! $name) {
            return ['status' => self::SKIPPED, 'business_id' => null, 'message' => 'missing_name'];
        }

        $externalId = $record['external_id'] ?: ($record['source_url'] ?: $this->fingerprint($record));

        return DB::transaction(function () use ($record, $sourceKey, $externalId, $options) {
            $source = BusinessSource::query()->where('source', $sourceKey)->where('external_id', $externalId)->first();
            $existing = $source?->business_id ? Business::withTrashed()->find($source->business_id) : null;
            $reason = $existing ? 'same_source' : null;

            if (! $existing) {
                [$existing, $reason] = $this->findDuplicate($record);
            }

            if ($existing) {
                if ($existing->trashed()) {
                    $this->touchSource($existing->id, $sourceKey, $externalId, $record['source_url'] ?? null);

                    return ['status' => self::SKIPPED, 'business_id' => $existing->id, 'message' => 'deleted_listing'];
                }
                $changed = ($options['update_existing'] ?? true) ? $this->merge($existing, $record) : false;
                $this->touchSource($existing->id, $sourceKey, $externalId, $record['source_url'] ?? null);

                return ['status' => $changed ? self::MERGED : self::SKIPPED, 'business_id' => $existing->id, 'message' => 'duplicate:'.$reason];
            }

            $business = $this->create($record, $options);
            $this->touchSource($business->id, $sourceKey, $externalId, $record['source_url'] ?? null);

            return ['status' => self::CREATED, 'business_id' => $business->id, 'message' => null];
        });
    }

    /** @return array{0: ?Business, 1: ?string} */
    public function findDuplicate(array $record): array
    {
        $name = $record['name_en'] ?? $record['name_ar'];
        $pairs = [];
        foreach (array_filter([$record['phone'] ?? null, $record['whatsapp'] ?? null, ...($record['extra_phones'] ?? [])]) as $p) {
            $pairs[] = ['phone', $p];
        }
        foreach (array_filter([$record['email'] ?? null, ...($record['extra_emails'] ?? [])]) as $e) {
            $pairs[] = ['email', $e];
        }
        if ($domain = Normalizer::domain($record['website'] ?? null)) {
            $pairs[] = ['domain', $domain];
        }

        $scores = [];
        if ($pairs !== []) {
            $matches = BusinessIdentifier::query()
                ->where(function ($q) use ($pairs) {
                    foreach ($pairs as [$type, $value]) {
                        $q->orWhere(fn ($w) => $w->where('type', $type)->where('value', $value));
                    }
                })
                ->get(['business_id', 'type']);
            foreach ($matches as $m) {
                $scores[$m->business_id] = ($scores[$m->business_id] ?? 0) + ($m->type === 'phone' ? 1.0 : 1.5);
            }
        }

        $best = null;
        $bestScore = 0.0;
        $why = null;
        if ($scores !== []) {
            $candidates = Business::withTrashed()->whereIn('id', array_keys($scores))->get(['id', 'name_en', 'name_ar', 'deleted_at']);
            foreach ($candidates as $c) {
                $similarity = max(Normalizer::nameSimilarity($name, $c->name_en), Normalizer::nameSimilarity($name, $c->name_ar));
                $score = $scores[$c->id];
                // Shared numbers alone are not enough (group companies share switchboards): names must agree too.
                $isSame = $similarity >= 0.85
                    || ($score >= 1.0 && $similarity >= 0.6)
                    || ($score >= 2.5 && $similarity >= 0.4);
                if ($isSame && $score + $similarity > $bestScore) {
                    $best = $c;
                    $bestScore = $score + $similarity;
                    $why = 'contact';
                }
            }
        }

        if (! $best && ($key = Normalizer::nameKey($name))) {
            $location = $this->locations->match($record['city'] ?? null, $record['address'] ?? null);
            $sameName = Business::withTrashed()->where('name_key', $key)->limit(20)->get(['id', 'municipality_id', 'latitude', 'longitude', 'geo_precision', 'deleted_at']);
            foreach ($sameName as $c) {
                // Same name in another municipality → a different company or branch.
                if ($location['municipality_id'] && $c->municipality_id && $location['municipality_id'] !== $c->municipality_id) {
                    continue;
                }
                // Two exact pins more than 1 km apart → separate branches.
                if (($record['latitude'] ?? null) !== null && $c->latitude !== null && $c->geo_precision === 'exact'
                    && self::distanceKm($record['latitude'], $record['longitude'], $c->latitude, $c->longitude) > 1.0) {
                    continue;
                }
                $best = $c;
                $why = 'name';
                break;
            }
        }

        return [$best ? Business::withTrashed()->find($best->id) : null, $why];
    }

    private static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($a)));
    }

    private function create(array $record, array $options): Business
    {
        $categoryIds = $this->categories->match($record['categories'] ?? [], $record['tags'] ?? [], $record['name_en'] ?? null, ['name_ar' => $record['name_ar'] ?? null, 'description' => $record['description'] ?? null]);
        if (! empty($options['default_category_id']) && $this->categories->isFallback($categoryIds[0])) {
            $categoryIds = [(int) $options['default_category_id']];
        }
        $location = $this->locations->match($record['city'] ?? null, $record['address'] ?? null);

        $business = new Business;
        $business->forceFill([
            'category_id' => $categoryIds[0],
            'municipality_id' => $location['municipality_id'],
            'area_id' => $location['area_id'],
            'name_en' => $record['name_en'] ?? $record['name_ar'],
            'name_ar' => $record['name_ar'] ?? '',
            'description_en' => $record['description'] ?? null,
            'keywords' => $this->keywords($record),
            'services_text' => $record['tags'] ? Str::limit(implode(', ', $record['tags']), 2000, '') : null,
            'opening_hours_text' => $record['hours'] ?? null,
            'address_en' => $record['address'] ?? null,
            'latitude' => $record['latitude'] ?? null,
            'longitude' => $record['longitude'] ?? null,
            'geo_precision' => $record['precision'] ?? null,
            'phone' => isset($record['phone']) ? Normalizer::displayPhone($record['phone']) : null,
            'whatsapp' => isset($record['whatsapp']) ? Normalizer::displayPhone($record['whatsapp']) : null,
            'extra_phones' => array_map(fn ($p) => Normalizer::displayPhone($p), $record['extra_phones'] ?? []) ?: null,
            'email' => $record['email'] ?? null,
            'extra_emails' => ($record['extra_emails'] ?? []) ?: null,
            'website' => $record['website'] ?? null,
            'imported_at' => now(),
            'is_demo' => false,
        ]);
        $business->slug = $this->slugs->unique(Business::class, $business->name_en);

        $publishable = ($options['publish'] ?? true) && $this->isPublishable($record);
        $business->status = $publishable ? BusinessStatus::Approved : BusinessStatus::Pending;
        $business->submitted_at = now();
        if ($publishable) {
            $business->approved_at = now();
            $business->published_at = now();
        }
        $business->save();

        if (count($categoryIds) > 1) {
            $business->categories()->sync(array_slice($categoryIds, 1, 3));
        }
        $this->addSocial($business, $record['social'] ?? []);

        return $business;
    }

    /** Fill only empty fields; never touch listings an owner has claimed. */
    private function merge(Business $business, array $record): bool
    {
        if ($business->owner_id !== null) {
            return false;
        }

        $fill = [];
        $set = function (string $column, mixed $value) use ($business, &$fill) {
            if ($value !== null && $value !== '' && $value !== [] && ($business->{$column} === null || $business->{$column} === '' || $business->{$column} === [])) {
                $fill[$column] = $value;
            }
        };

        $set('name_ar', $record['name_ar'] ?? null);
        $set('phone', isset($record['phone']) ? Normalizer::displayPhone($record['phone']) : null);
        $set('whatsapp', isset($record['whatsapp']) ? Normalizer::displayPhone($record['whatsapp']) : null);
        $set('email', $record['email'] ?? null);
        $set('website', $record['website'] ?? null);
        $set('address_en', $record['address'] ?? null);
        $set('description_en', $record['description'] ?? null);
        $set('opening_hours_text', $record['hours'] ?? null);
        if (($record['latitude'] ?? null) !== null && $business->latitude === null) {
            $fill += ['latitude' => $record['latitude'], 'longitude' => $record['longitude'], 'geo_precision' => $record['precision']];
        }
        if ($business->municipality_id === null) {
            $location = $this->locations->match($record['city'] ?? null, $record['address'] ?? null);
            $set('municipality_id', $location['municipality_id']);
            $set('area_id', $location['area_id']);
        }

        // Union of extra numbers / e-mails not already known.
        $knownPhones = array_filter(array_map(fn ($p) => Normalizer::phone($p), [$business->phone, $business->whatsapp, ...(array) $business->extra_phones, $fill['phone'] ?? null, $fill['whatsapp'] ?? null]));
        $newPhones = array_diff(array_filter([$record['phone'] ?? null, $record['whatsapp'] ?? null, ...($record['extra_phones'] ?? [])]), $knownPhones);
        if ($newPhones) {
            $fill['extra_phones'] = array_slice(array_values(array_unique([...(array) $business->extra_phones, ...array_map(fn ($p) => Normalizer::displayPhone($p), $newPhones)])), 0, 10);
        }
        $knownEmails = array_filter([$business->email, ...(array) $business->extra_emails, $fill['email'] ?? null]);
        $newEmails = array_diff(array_filter([$record['email'] ?? null, ...($record['extra_emails'] ?? [])]), $knownEmails);
        if ($newEmails) {
            $fill['extra_emails'] = array_slice(array_values(array_unique([...(array) $business->extra_emails, ...$newEmails])), 0, 10);
        }

        // A better category than "Other businesses".
        if ($this->categories->isFallback((int) $business->category_id)) {
            $ids = $this->categories->match($record['categories'] ?? [], $record['tags'] ?? [], $business->name_en, ['name_ar' => $business->name_ar, 'description' => $record['description'] ?? null]);
            if (! $this->categories->isFallback($ids[0])) {
                $fill['category_id'] = $ids[0];
            }
        }

        // Another trading name for the same company stays searchable ("also known as").
        $incoming = $record['name_en'] ?? $record['name_ar'];
        if ($incoming && max(Normalizer::nameSimilarity($incoming, $business->name_en), Normalizer::nameSimilarity($incoming, $business->name_ar)) < 0.85
            && ! str_contains(Str::lower((string) $business->keywords), Str::lower($incoming))) {
            $fill['keywords'] = Str::limit(trim(($business->keywords ? $business->keywords.', ' : '').$incoming, ', '), 500, '');
        }

        $socialAdded = $this->addSocial($business, $record['social'] ?? []);

        if ($fill === []) {
            return $socialAdded;
        }
        if (! isset($fill['keywords']) && ! $business->keywords && ($kw = $this->keywords($record))) {
            $fill['keywords'] = $kw;
        }
        $business->forceFill($fill)->save();

        return true;
    }

    /** A listing goes live straight away only with a name and at least one way to reach or find it. */
    private function isPublishable(array $record): bool
    {
        return ($record['name_en'] ?? $record['name_ar'] ?? null) !== null
            && ($record['phone'] || $record['whatsapp'] || $record['email'] || $record['website'] || $record['address'] || $record['latitude'] !== null);
    }

    private function addSocial(Business $business, array $social): bool
    {
        if ($social === []) {
            return false;
        }
        $existing = $business->socialLinks()->pluck('platform')->map(fn ($p) => $p instanceof \BackedEnum ? $p->value : $p)->all();
        $added = false;
        foreach ($social as $platform => $url) {
            if (! in_array($platform, $existing, true)) {
                $business->socialLinks()->create(['platform' => $platform, 'url' => $url]);
                $added = true;
            }
        }

        return $added;
    }

    private function touchSource(int $businessId, string $source, string $externalId, ?string $url): void
    {
        BusinessSource::query()->upsert(
            [['business_id' => $businessId, 'source' => $source, 'external_id' => Str::limit($externalId, 191, ''), 'url' => $url ? Str::limit($url, 500, '') : null, 'first_seen_at' => now(), 'last_seen_at' => now()]],
            ['source', 'external_id'],
            ['business_id', 'url', 'last_seen_at'],
        );
    }

    private function keywords(array $record): ?string
    {
        $words = array_unique(array_filter([...($record['categories'] ?? []), ...array_slice($record['tags'] ?? [], 0, 8)]));

        return $words ? Str::limit(implode(', ', $words), 500, '') : null;
    }

    private function fingerprint(array $record): string
    {
        return 'fp:'.sha1(implode('|', [Normalizer::nameKey($record['name_en'] ?? $record['name_ar']), $record['phone'] ?? '', $record['email'] ?? '', $record['address'] ?? '']));
    }
}
