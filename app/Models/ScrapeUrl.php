<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScrapeUrl extends Model
{
    protected $fillable = ['scrape_source_id', 'url', 'url_hash', 'status'];

    protected function casts(): array
    {
        return ['fetched_at' => 'datetime'];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ScrapeSource::class, 'scrape_source_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
