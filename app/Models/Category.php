<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasTranslations;

    protected $fillable = [
        'parent_id', 'name_ar', 'name_en', 'slug', 'description_ar', 'description_en', 'icon',
        'image_path', 'sort_order', 'is_active', 'is_featured',
        'meta_title_ar', 'meta_title_en', 'meta_description_ar', 'meta_description_en',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_featured' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name_en');
    }

    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class);
    }

    public function secondaryBusinesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'business_category');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeRoots(Builder $q): Builder
    {
        return $q->whereNull('parent_id');
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('name_en');
    }

    /** IDs of this category and its direct sub-categories (used for filtering). */
    public function familyIds(): array
    {
        return array_merge([$this->id], $this->children()->pluck('id')->all());
    }

    public function isReferenced(): bool
    {
        return $this->businesses()->withTrashed()->exists()
            || $this->secondaryBusinesses()->exists()
            || $this->children()->exists();
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? media_url($this->image_path) : null;
    }
}
