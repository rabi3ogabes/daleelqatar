<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessService extends Model
{
    use HasTranslations;

    protected $fillable = ['name_ar', 'name_en', 'description_ar', 'description_en', 'price_from', 'sort_order'];

    protected function casts(): array
    {
        return ['price_from' => 'decimal:2'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
