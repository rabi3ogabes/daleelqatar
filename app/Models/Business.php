<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BusinessStatus;
use App\Enums\ModerationStatus;
use App\Enums\OfferStatus;
use App\Models\Concerns\HasTranslations;
use App\Services\IndexNow;
use App\Support\Import\BusinessIdentity;
use App\Support\Import\Normalizer;
use Carbon\CarbonImmutable;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory, HasTranslations, HasUuids, SoftDeletes;

    public const TIMEZONE = 'Asia/Qatar';

    /**
     * Mass-assignable attributes that owners may edit. Moderation, verification,
     * featuring and aggregate columns are never mass assignable.
     */
    protected $fillable = [
        'category_id', 'municipality_id', 'area_id',
        'name_ar', 'name_en', 'tagline_ar', 'tagline_en', 'description_ar', 'description_en', 'keywords',
        'address_ar', 'address_en', 'latitude', 'longitude',
        'phone', 'whatsapp', 'email', 'website',
        'meta_title_ar', 'meta_title_en', 'meta_description_ar', 'meta_description_en',
    ];

    protected $hidden = ['search_document', 'search_vector', 'moderation_notes'];

    protected function casts(): array
    {
        return [
            'status' => BusinessStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
            'featured_until' => 'datetime',
            'verified_at' => 'datetime',
            'is_featured' => 'boolean',
            'is_verified' => 'boolean',
            'is_demo' => 'boolean',
            'rating_avg' => 'float',
            'latitude' => 'float',
            'longitude' => 'float',
            'extra_phones' => 'array',
            'extra_emails' => 'array',
            'imported_at' => 'datetime',
            'enriched_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // The comparison key used to spot duplicate listings with the same name.
        static::saving(function (Business $business) {
            if ($business->isDirty(['name_en', 'name_ar']) || $business->name_key === null) {
                $business->name_key = Normalizer::nameKey($business->name_en ?: (string) $business->name_ar);
            }
        });

        // Phones, e-mails and website domains are indexed so imports never duplicate a listing.
        static::saved(function (Business $business) {
            if ($business->wasRecentlyCreated || $business->wasChanged(['phone', 'whatsapp', 'email', 'website', 'extra_phones', 'extra_emails'])) {
                BusinessIdentity::sync($business);
            }
            if ($business->wasRecentlyCreated || $business->wasChanged()) {
                IndexNow::queue($business);
            }
            // A category chosen by hand wins over an open suggestion from the category check.
            if ($business->wasChanged('category_id')) {
                CategoryReview::query()->where('business_id', $business->id)->where('status', 'pending')
                    ->update(['status' => 'kept', 'current_category_id' => $business->category_id, 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
            }
        });
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // ---------------------------------------------------------------- relations

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'business_category');
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(BusinessImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(BusinessService::class)->orderBy('sort_order')->orderBy('id');
    }

    public function openingHours(): HasMany
    {
        return $this->hasMany(OpeningHour::class)->orderBy('day_of_week')->orderBy('opens_at');
    }

    public function specialHours(): HasMany
    {
        return $this->hasMany(SpecialHour::class)->orderBy('day_of_week')->orderBy('opens_at');
    }

    public function quoteRecipients(): HasMany
    {
        return $this->hasMany(QuoteRecipient::class);
    }

    public function socialLinks(): HasMany
    {
        return $this->hasMany(BusinessSocialLink::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('status', ModerationStatus::Approved);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(BusinessStatusChange::class)->latest('created_at')->latest('id');
    }

    public function claimRequests(): HasMany
    {
        return $this->hasMany(BusinessClaimRequest::class);
    }

    public function verificationRequests(): HasMany
    {
        return $this->hasMany(BusinessVerificationRequest::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ListingReport::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(BusinessEvent::class);
    }

    public function identifiers(): HasMany
    {
        return $this->hasMany(BusinessIdentifier::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(BusinessSource::class);
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ------------------------------------------------------------------- scopes

    /** Listings visible to the public. */
    public function scopePublished(Builder $q): Builder
    {
        return $q->where('businesses.status', BusinessStatus::Approved)->whereNotNull('businesses.published_at');
    }

    public function scopeFeatured(Builder $q): Builder
    {
        return $q->where('businesses.is_featured', true)
            ->where(fn (Builder $w) => $w->whereNull('businesses.featured_until')->orWhere('businesses.featured_until', '>', now()));
    }

    public function scopeVerified(Builder $q): Builder
    {
        return $q->where('businesses.is_verified', true);
    }

    public function scopeInCategory(Builder $q, Category $category): Builder
    {
        $ids = $category->familyIds();

        return $q->where(fn (Builder $w) => $w->whereIn('businesses.category_id', $ids)
            ->orWhereExists(fn ($s) => $s->selectRaw('1')->from('business_category')
                ->whereColumn('business_category.business_id', 'businesses.id')
                ->whereIn('business_category.category_id', $ids)));
    }

    /**
     * Businesses open right now in Qatar time, including periods that run past midnight.
     * While a season (Ramadan, Eid…) runs, a business's special hours replace its regular ones.
     */
    public function scopeOpenNow(Builder $q, ?CarbonImmutable $at = null): Builder
    {
        $now = ($at ?? CarbonImmutable::now(self::TIMEZONE))->setTimezone(self::TIMEZONE);
        $day = $now->dayOfWeek;
        $prev = ($day + 6) % 7;
        $time = $now->format('H:i:s');
        $seasonIds = SeasonalPeriod::current($now)->pluck('id')->all();

        $openIn = function (string $table, ?array $periods) use ($day, $prev, $time) {
            return function ($s) use ($table, $periods, $day, $prev, $time) {
                $s->selectRaw('1')->from($table.' as oh')
                    ->whereColumn('oh.business_id', 'businesses.id')
                    ->when($periods !== null, fn ($w) => $w->whereIn('oh.seasonal_period_id', $periods))
                    ->where('oh.is_closed', false)
                    ->where(function ($w) use ($day, $prev, $time) {
                        $w->where(fn ($a) => $a->where('oh.day_of_week', $day)
                            ->whereColumn('oh.opens_at', '<=', 'oh.closes_at')
                            ->where('oh.opens_at', '<=', $time)->where('oh.closes_at', '>', $time))
                            ->orWhere(fn ($b) => $b->where('oh.day_of_week', $day)
                                ->whereColumn('oh.opens_at', '>', 'oh.closes_at')
                                ->where('oh.opens_at', '<=', $time))
                            ->orWhere(fn ($c) => $c->where('oh.day_of_week', $prev)
                                ->whereColumn('oh.opens_at', '>', 'oh.closes_at')
                                ->where('oh.closes_at', '>', $time));
                    });
            };
        };

        if ($seasonIds === []) {
            return $q->whereExists($openIn('opening_hours', null));
        }

        return $q->where(fn (Builder $w) => $w
            ->whereExists($openIn('special_hours', $seasonIds))
            ->orWhere(fn (Builder $r) => $r
                ->whereNotExists(fn ($s) => $s->selectRaw('1')->from('special_hours as sh')
                    ->whereColumn('sh.business_id', 'businesses.id')->whereIn('sh.seasonal_period_id', $seasonIds))
                ->whereExists($openIn('opening_hours', null))));
    }

    // ------------------------------------------------------------------ helpers

    public function isPublished(): bool
    {
        return $this->status === BusinessStatus::Approved && $this->published_at !== null && ! $this->trashed();
    }

    public function isFeaturedNow(): bool
    {
        return $this->is_featured && ($this->featured_until === null || $this->featured_until->isFuture());
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $this->owner_id !== null && $this->owner_id === $user->id;
    }

    public function hasOpeningHours(): bool
    {
        if ($this->seasonalHours() !== null) {
            return true;
        }

        return $this->relationLoaded('openingHours') ? $this->openingHours->isNotEmpty() : $this->openingHours()->exists();
    }

    /** @var array{0: ?SeasonalPeriod, 1: ?Collection}|null */
    private ?array $seasonCache = null;

    /** The season running today for which this business set special hours (null when none). */
    public function currentSeason(): ?SeasonalPeriod
    {
        return $this->seasonState()[0];
    }

    /** Special hours replacing the regular ones today, or null. */
    public function seasonalHours(): ?Collection
    {
        return $this->seasonState()[1];
    }

    /** The hours that apply today: a season's special hours, otherwise the regular week. */
    public function effectiveHours(): Collection
    {
        return $this->seasonalHours() ?? $this->openingHours;
    }

    /** @return array{0: ?SeasonalPeriod, 1: ?Collection} */
    private function seasonState(): array
    {
        if ($this->seasonCache !== null) {
            return $this->seasonCache;
        }
        $periods = SeasonalPeriod::current();
        if ($periods->isEmpty() || ! $this->exists) {
            return $this->seasonCache = [null, null];
        }
        $rows = $this->relationLoaded('specialHours')
            ? $this->specialHours->whereIn('seasonal_period_id', $periods->pluck('id')->all())
            : $this->specialHours()->whereIn('seasonal_period_id', $periods->pluck('id')->all())->get();
        foreach ($periods as $period) {
            $own = $rows->where('seasonal_period_id', $period->id)->values();
            if ($own->isNotEmpty()) {
                return $this->seasonCache = [$period, $own];
            }
        }

        return $this->seasonCache = [null, null];
    }

    /** null when no reliable hours exist. */
    public function isOpenNow(?CarbonImmutable $at = null): ?bool
    {
        if (! $this->hasOpeningHours()) {
            return null;
        }

        $now = ($at ?? CarbonImmutable::now(self::TIMEZONE))->setTimezone(self::TIMEZONE);
        $time = $now->format('H:i:s');
        $day = $now->dayOfWeek;
        $prev = ($day + 6) % 7;

        foreach ($this->effectiveHours() as $h) {
            if ($h->is_closed || ! $h->opens_at || ! $h->closes_at) {
                continue;
            }
            $o = $h->opens_at;
            $c = $h->closes_at;
            if ($h->day_of_week === $day && $o <= $c && $o <= $time && $time < $c) {
                return true;
            }
            if ($h->day_of_week === $day && $o > $c && $o <= $time) {
                return true;
            }
            if ($h->day_of_week === $prev && $o > $c && $time < $c) {
                return true;
            }
        }

        return false;
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? media_url($this->logo_path) : null;
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? media_url($this->cover_path) : null;
    }

    /** Best image for cards: cover → first gallery image → logo. */
    public function cardImageUrl(): ?string
    {
        if ($this->cover_path) {
            return $this->coverUrl();
        }
        if ($this->relationLoaded('images') && $this->images->isNotEmpty()) {
            return $this->images->first()->url();
        }

        return null;
    }

    public function locationLabel(?string $locale = null): ?string
    {
        $parts = array_filter([$this->area?->t('name', $locale), $this->municipality?->t('name', $locale)]);

        return $parts ? implode(locale_is_rtl($locale) ? '، ' : ', ', $parts) : null;
    }

    public function whatsappUrl(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->whatsapp);

        return $digits ? 'https://wa.me/'.$digits : null;
    }

    public function telUrl(): ?string
    {
        $digits = preg_replace('/[^\d+]/', '', (string) $this->phone);

        return $digits ? 'tel:'.$digits : null;
    }

    public function directionsUrl(): ?string
    {
        return $this->latitude !== null && $this->longitude !== null
            ? 'https://www.google.com/maps/dir/?api=1&destination='.$this->latitude.','.$this->longitude
            : null;
    }

    /** Active, approved offers currently running. */
    public function activeOffers(): HasMany
    {
        return $this->offers()->where('status', OfferStatus::Approved)->where('is_active', true)
            ->where('starts_at', '<=', now())->where('ends_at', '>', now());
    }
}
