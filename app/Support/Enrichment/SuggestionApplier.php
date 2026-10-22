<?php

declare(strict_types=1);

namespace App\Support\Enrichment;

use App\Models\Business;
use App\Models\BusinessSuggestion;
use App\Models\Category;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BusinessManager;
use App\Support\Directory;
use App\Support\Discovery\DiscoveryException;
use App\Support\Discovery\Fetcher;
use App\Support\Import\Normalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Writes the fields an admin approved (with any edits) to the listing.
 * Values are validated again here: the review form is never trusted blindly.
 */
final class SuggestionApplier
{
    private const TEXT_LIMITS = [
        'name_ar' => 160, 'tagline_en' => 200, 'tagline_ar' => 200, 'description_en' => 5000, 'description_ar' => 5000,
        'address_en' => 300, 'address_ar' => 300,
    ];

    public function __construct(private readonly BusinessManager $manager, private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $approved  field => edited value (null = keep the suggested value)
     *
     * @throws ValidationException
     */
    public function apply(BusinessSuggestion $suggestion, array $approved, User $by): BusinessSuggestion
    {
        abort_unless($suggestion->isPending(), 409);
        $approved = array_intersect_key($approved, $suggestion->fields);
        if ($approved === []) {
            throw ValidationException::withMessages(['fields' => __('enrichment.errors.nothing_selected')]);
        }

        $values = [];
        foreach ($approved as $field => $edited) {
            $values[$field] = $this->clean($field, $edited ?? $suggestion->fields[$field]['value'], $suggestion->business_id);
        }
        // A logo from another website is downloaded and checked before anything is saved.
        $logo = isset($values['logo']) ? $this->logo($values['logo']) : null;

        $suggestion = DB::transaction(function () use ($suggestion, $values, $by) {
            /** @var Business $business */
            $business = Business::query()->lockForUpdate()->findOrFail($suggestion->business_id);
            $original = $business->getAttributes();

            foreach ($values as $field => $value) {
                match ($field) {
                    'name_ar', 'tagline_en', 'tagline_ar', 'description_en', 'description_ar', 'address_en', 'address_ar', 'phone', 'whatsapp' => $business->{$field} = $value,
                    'website' => $business->forceFill(['website' => $value, 'website_status' => 'found']),
                    'location' => $business->forceFill(['latitude' => $value['lat'], 'longitude' => $value['lng'], 'geo_precision' => 'exact']),
                    'google_place' => $business->forceFill(['google_place_id' => $value['place_id'], 'google_maps_url' => $value['maps_url']]),
                    'email' => $business->email = $value,
                    'extra_phones' => $business->extra_phones = array_slice(array_values(array_unique([...(array) $business->extra_phones, ...$value])), 0, 10),
                    'extra_emails' => $business->extra_emails = array_slice(array_values(array_unique([...(array) $business->extra_emails, ...$value])), 0, 10),
                    'category' => $business->category_id = $value,
                    default => null,
                };
            }
            $business->save();

            if (isset($values['opening_hours'])) {
                $this->manager->syncHours($business, $values['opening_hours']['days']);
            }
            if (isset($values['services'])) {
                $this->manager->syncServices($business, $values['services']);
            }
            if (isset($values['social'])) {
                $this->manager->syncSocial($business, $values['social']);
            }

            $suggestion->forceFill([
                'status' => count($values) === count($suggestion->fields) ? 'applied' : 'partial',
                'applied_fields' => $values,
                'reviewed_by' => $by->id,
                'reviewed_at' => now(),
            ])->save();

            $this->audit->logChanges('enrichment.applied', $business, $original, [
                'suggestion_id' => $suggestion->id,
                'fields' => array_keys($values),
                'sources' => array_values(array_unique(array_map(fn ($f) => $suggestion->fields[$f]['source'] ?? null, array_keys($values)))),
            ]) ?? $this->audit->log('enrichment.applied', $business, [], [], ['suggestion_id' => $suggestion->id, 'fields' => array_keys($values)]);

            if (isset($values['location'])) {
                Cache::forget('map.points.ar');
                Cache::forget('map.points.en');
            }
            Directory::flushCounts();

            return $suggestion;
        });

        if ($logo !== null) {
            $business = Business::query()->findOrFail($suggestion->business_id);
            $this->manager->setLogoPng($business, $logo['png'], ['origin' => 'directory', 'source_url' => $logo['url']]);
        }

        return $suggestion;
    }

    /** @return array{png: string, url: string} */
    private function logo(string $url): array
    {
        try {
            $image = (new Fetcher(0, 10))->get($url);
            $png = LogoFinder::accept($image->body);
        } catch (DiscoveryException) {
            $png = null;
        }
        if ($png === null) {
            throw ValidationException::withMessages(['fields.logo' => __('enrichment.errors.logo')]);
        }

        return ['png' => $png, 'url' => $image->url];
    }

    public function reject(BusinessSuggestion $suggestion, User $by): void
    {
        abort_unless($suggestion->isPending(), 409);
        $suggestion->forceFill(['status' => 'rejected', 'reviewed_by' => $by->id, 'reviewed_at' => now()])->save();
        $this->audit->log('enrichment.rejected', $suggestion->business, [], [], ['suggestion_id' => $suggestion->id]);
    }

    private function clean(string $field, mixed $value, int $businessId): mixed
    {
        $fail = fn (string $key = 'invalid') => throw ValidationException::withMessages(["fields.$field" => __('enrichment.errors.'.$key, ['field' => __('enrichment.fields.'.$field)])]);

        if (isset(self::TEXT_LIMITS[$field])) {
            $value = trim((string) (is_scalar($value) ? $value : ''));
            if ($value === '') {
                $fail('required');
            }
            if ($field === 'name_ar' && ! Normalizer::hasArabic($value)) {
                $fail('arabic');
            }

            return mb_substr(strip_tags($value), 0, self::TEXT_LIMITS[$field]);
        }

        return match ($field) {
            'phone', 'whatsapp' => ($p = Normalizer::phone(is_scalar($value) ? (string) $value : '')) ? Normalizer::displayPhone($p) : $fail(),
            'website' => (($url = safe_external_url(Normalizer::website(is_scalar($value) ? (string) $value : ''))) !== null) ? $url : $fail(),
            'location' => $this->location($value) ?? $fail(),
            'google_place' => $this->place($value, $businessId) ?? $fail(),
            'opening_hours' => is_array($value) && is_array($value['days'] ?? null) ? ['days' => $value['days']] : $fail(),
            'services' => $this->services($value) ?: $fail('required'),
            'social' => $this->social($value) ?: $fail(),
            'email' => ($e = $this->email($value)) !== null ? $e : $fail(),
            'extra_emails' => $this->list($value, fn ($v) => $this->email($v)) ?: $fail(),
            'extra_phones' => $this->list($value, fn ($v) => ($p = Normalizer::phone($v)) ? Normalizer::displayPhone($p) : null) ?: $fail(),
            'category' => Category::query()->whereKey(is_array($value) ? ($value['id'] ?? null) : $value)->where('is_active', true)->value('id') ?? $fail(),
            'logo' => ($url = safe_external_url(is_scalar($value) ? (string) $value : '')) !== null ? $url : $fail(),
            default => $fail(),
        };
    }

    private function email(mixed $value): ?string
    {
        $email = strtolower(trim(is_scalar($value) ? (string) $value : ''));

        return $email !== '' && strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /** A list typed as "a, b" or sent as an array, each item cleaned; invalid items are dropped. @return list<string> */
    private function list(mixed $value, \Closure $clean): array
    {
        $items = is_array($value) ? $value : preg_split('/[,;\n]+/', is_scalar($value) ? (string) $value : '');

        return array_slice(array_values(array_unique(array_filter(array_map(fn ($v) => is_scalar($v) ? $clean(trim((string) $v)) : null, $items ?: [])))), 0, 10);
    }

    private function location(mixed $value): ?array
    {
        if (is_string($value) && preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/', $value, $m)) {
            $value = ['lat' => $m[1], 'lng' => $m[2]];
        }
        $lat = is_array($value) && is_numeric($value['lat'] ?? null) ? (float) $value['lat'] : null;
        $lng = is_array($value) && is_numeric($value['lng'] ?? null) ? (float) $value['lng'] : null;

        // Inside Qatar only.
        return $lat !== null && $lng !== null && $lat >= 24.4 && $lat <= 26.3 && $lng >= 50.7 && $lng <= 51.8
            ? ['lat' => round($lat, 7), 'lng' => round($lng, 7)] : null;
    }

    private function place(mixed $value, int $businessId): ?array
    {
        $id = is_array($value) ? (string) ($value['place_id'] ?? '') : '';
        if (! preg_match('/^[A-Za-z0-9_\-]{10,255}$/', $id)) {
            return null;
        }
        // A Google place belongs to one listing: a second link usually means a duplicate listing.
        $taken = Business::query()->withTrashed()->where('google_place_id', $id)->whereKeyNot($businessId)->value('name_en');
        if ($taken !== null) {
            throw ValidationException::withMessages(['fields.google_place' => __('enrichment.errors.place_taken', ['name' => $taken])]);
        }
        $url = safe_external_url((string) ($value['maps_url'] ?? '')) ?? GooglePlaces::mapsUrl($id);

        return ['place_id' => $id, 'maps_url' => mb_substr($url, 0, 500)];
    }

    /** @return list<array{name_en: string, name_ar: string}> */
    private function services(mixed $value): array
    {
        $out = [];
        foreach (array_slice(is_array($value) ? array_values($value) : [], 0, 30) as $row) {
            $en = trim(strip_tags((string) ($row['name_en'] ?? '')));
            $ar = trim(strip_tags((string) ($row['name_ar'] ?? '')));
            if ($en !== '' || $ar !== '') {
                $out[] = ['name_en' => mb_substr($en ?: $ar, 0, 160), 'name_ar' => mb_substr($ar ?: $en, 0, 160)];
            }
        }

        return $out;
    }

    /** @return array<string, string> */
    private function social(mixed $value): array
    {
        $allowed = ['instagram', 'x', 'facebook', 'tiktok', 'snapchat', 'linkedin', 'youtube'];
        $out = [];
        foreach (is_array($value) ? $value : [] as $platform => $url) {
            if (in_array($platform, $allowed, true) && ($url = safe_external_url((string) $url)) !== null) {
                $out[$platform] = mb_substr($url, 0, 300);
            }
        }

        return $out;
    }
}
