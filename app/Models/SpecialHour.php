<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Opening hours that replace the regular ones while a seasonal period runs. */
class SpecialHour extends OpeningHour
{
    protected $table = 'special_hours';

    protected $fillable = ['seasonal_period_id', 'day_of_week', 'opens_at', 'closes_at', 'is_closed'];

    public function period(): BelongsTo
    {
        return $this->belongsTo(SeasonalPeriod::class, 'seasonal_period_id');
    }
}
