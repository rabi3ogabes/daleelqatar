<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningHour extends Model
{
    protected $fillable = ['day_of_week', 'opens_at', 'closes_at', 'is_closed'];

    protected function casts(): array
    {
        return ['day_of_week' => 'integer', 'is_closed' => 'boolean'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function isAllDay(): bool
    {
        return ! $this->is_closed && str_starts_with((string) $this->opens_at, '00:00') && str_starts_with((string) $this->closes_at, '24:00');
    }

    public function formatted(): string
    {
        if ($this->is_closed) {
            return __('business.closed');
        }
        if ($this->isAllDay()) {
            return __('business.open_24h');
        }

        return format_time((string) $this->opens_at).' – '.format_time((string) $this->closes_at);
    }
}
