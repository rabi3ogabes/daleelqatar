<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Where a listing was imported or discovered from (file name or crawler source + its record id). */
class BusinessSource extends Model
{
    public $timestamps = false;

    protected $fillable = ['business_id', 'source', 'external_id', 'url', 'first_seen_at', 'last_seen_at'];

    protected function casts(): array
    {
        return ['first_seen_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
