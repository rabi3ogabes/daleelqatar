<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BusinessStatus;
use App\Enums\OfferStatus;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Offer extends Model
{
    use HasFactory, HasTranslations, HasUuids, SoftDeletes;

    protected $fillable = [
        'title_ar', 'title_en', 'description_ar', 'description_en', 'discount_label_ar', 'discount_label_en',
        'terms_ar', 'terms_en', 'starts_at', 'ends_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'status' => OfferStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'approved_at' => 'datetime',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Approved, enabled, running now, and belonging to a published listing. Expired offers never match. */
    public function scopeLive(Builder $q): Builder
    {
        return $q->where('offers.status', OfferStatus::Approved)
            ->where('offers.is_active', true)
            ->where('offers.starts_at', '<=', now())
            ->where('offers.ends_at', '>', now())
            ->whereHas('business', fn (Builder $b) => $b->where('status', BusinessStatus::Approved)->whereNotNull('published_at')->whereNull('deleted_at'));
    }

    public function isLive(): bool
    {
        return $this->status === OfferStatus::Approved && $this->is_active
            && $this->starts_at->isPast() && $this->ends_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->ends_at->isPast();
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? media_url($this->image_path) : null;
    }
}
