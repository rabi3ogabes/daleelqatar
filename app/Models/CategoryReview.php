<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The category check of one listing: the category it should be in according
 * to the classifier (and the AI's second opinion), how sure that is and why.
 *
 * status: pending (a change is proposed, or no category could be worked out),
 *         correct (the current category is right), applied, kept (an admin
 *         decided to keep the listing where it is).
 * kind:   fix (a different category), refine (a more specific child of the
 *         current one), same.
 * reasons: list of { field, text, keyword?, text_ar? }
 */
class CategoryReview extends Model
{
    public const HIGH = 80;

    public const MEDIUM = 55;

    protected $fillable = [
        'business_id', 'current_category_id', 'suggested_category_id', 'secondary_ids', 'confidence', 'method', 'kind', 'status', 'reasons',
    ];

    protected function casts(): array
    {
        return [
            'secondary_ids' => 'array',
            'reasons' => 'array',
            'confidence' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function current(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'current_category_id');
    }

    public function suggested(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'suggested_category_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', 'pending');
    }

    /** Pending changes the admin can act on (a category was found). */
    public function scopeProposed(Builder $q): Builder
    {
        return $q->where('status', 'pending')->whereNotNull('suggested_category_id');
    }

    public function scopeLevel(Builder $q, ?string $level): Builder
    {
        return match ($level) {
            'high' => $q->where('confidence', '>=', self::HIGH),
            'medium' => $q->whereBetween('confidence', [self::MEDIUM, self::HIGH - 1]),
            'low' => $q->where('confidence', '<', self::MEDIUM),
            default => $q,
        };
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function level(): string
    {
        return $this->confidence >= self::HIGH ? 'high' : ($this->confidence >= self::MEDIUM ? 'medium' : 'low');
    }
}
