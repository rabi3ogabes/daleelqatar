<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** An editorial landing page ("Pharmacies open 24 hours in Doha") built from real listings. */
class Guide extends Model
{
    use HasTranslations;

    protected $fillable = [
        'slug', 'title_ar', 'title_en', 'intro_ar', 'intro_en', 'meta_description_ar', 'meta_description_en',
        'category_id', 'municipality_id', 'area_id', 'open_24h', 'verified_only', 'max_items',
    ];

    protected function casts(): array
    {
        return [
            'open_24h' => 'boolean',
            'verified_only' => 'boolean',
            'max_items' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
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

    public function pinned(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'guide_business')->withPivot('sort_order')->orderByPivot('sort_order');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('guides.status', 'published')->whereNotNull('guides.published_at');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_at !== null;
    }
}
