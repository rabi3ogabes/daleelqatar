<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Proposed improvements to a listing (from Google Maps, the company website
 * or the AI assistant). Nothing reaches the listing until an admin approves.
 *
 * fields: { field: { value, current, source, confidence, note? } }
 */
class BusinessSuggestion extends Model
{
    public const SOURCES = ['google', 'website', 'email', 'ai', 'inferred'];

    protected $fillable = ['business_id', 'status', 'match_confidence', 'match', 'fields', 'sources', 'notes'];

    protected function casts(): array
    {
        return [
            'match' => 'array',
            'fields' => 'array',
            'sources' => 'array',
            'notes' => 'array',
            'applied_fields' => 'array',
            'reviewed_at' => 'datetime',
            'match_confidence' => 'integer',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class)->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', 'pending');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /** high (≥ 80), medium (≥ 55) or low — how sure the Google match is. */
    public function confidenceLevel(): ?string
    {
        if ($this->match_confidence === null) {
            return null;
        }

        return $this->match_confidence >= 80 ? 'high' : ($this->match_confidence >= 55 ? 'medium' : 'low');
    }
}
