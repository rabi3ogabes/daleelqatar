<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModerationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Review extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['rating', 'title', 'body', 'locale'];

    protected function casts(): array
    {
        return [
            'status' => ModerationStatus::class,
            'rating' => 'integer',
            'owner_replied_at' => 'datetime',
            'moderated_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ReviewReport::class);
    }

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('reviews.status', ModerationStatus::Approved);
    }

    /** Public display name: first name and initial only, to protect privacy. */
    public function authorDisplayName(): string
    {
        $parts = preg_split('/\s+/u', trim((string) $this->user?->name)) ?: [];
        $first = $parts[0] ?? __('common.anonymous');
        $last = isset($parts[1]) ? ' '.mb_substr($parts[1], 0, 1).'.' : '';

        return $first.$last;
    }
}
