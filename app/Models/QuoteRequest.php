<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuoteStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One customer request sent to a handful of matching businesses. */
class QuoteRequest extends Model
{
    use HasUuids;

    public const TIMINGS = ['asap', 'this_week', 'this_month', 'flexible'];

    public const CONTACT_METHODS = ['phone', 'whatsapp', 'email'];

    public const CLOSE_REASONS = ['found', 'no_longer_needed', 'other'];

    protected $fillable = [
        'category_id', 'municipality_id', 'area_id', 'origin_business_id',
        'title', 'details', 'timing', 'contact_method', 'contact_phone', 'locale',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'expires_at' => 'datetime',
            'closed_at' => 'datetime',
            'recipients_count' => 'integer',
            'replies_count' => 'integer',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function originBusiness(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'origin_business_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(QuoteRecipient::class)->orderBy('id');
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('quote_requests.status', QuoteStatus::Open)->where('quote_requests.expires_at', '>', now());
    }

    public function isOpen(): bool
    {
        return $this->status === QuoteStatus::Open && $this->expires_at->isFuture();
    }

    public function locationLabel(?string $locale = null): ?string
    {
        $parts = array_filter([$this->area?->t('name', $locale), $this->municipality?->t('name', $locale)]);

        return $parts ? implode(locale_is_rtl($locale) ? '، ' : ', ', $parts) : null;
    }

    /** The customer's contact details are shared only with businesses that received the request. */
    public function contactValue(): ?string
    {
        return match ($this->contact_method) {
            'email' => $this->user?->email,
            default => $this->contact_phone ?: $this->user?->phone,
        };
    }

    public function contactUrl(): ?string
    {
        $value = $this->contactValue();
        if (! $value) {
            return null;
        }

        return match ($this->contact_method) {
            'email' => 'mailto:'.$value,
            'whatsapp' => ($d = preg_replace('/\D+/', '', $value)) ? 'https://wa.me/'.$d : null,
            default => 'tel:'.preg_replace('/[^\d+]/', '', $value),
        };
    }
}
