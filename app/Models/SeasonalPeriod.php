<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/** Ramadan, Eid, National Day… dates when businesses may keep special hours. */
class SeasonalPeriod extends Model
{
    use HasTranslations;

    public const KINDS = ['ramadan', 'eid', 'national_day', 'other'];

    protected $fillable = ['slug', 'name_ar', 'name_en', 'kind', 'starts_on', 'ends_on', 'is_active'];

    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::forgetCache());
        static::deleted(fn () => self::forgetCache());
    }

    public static function forgetCache(): void
    {
        Cache::forget('seasonal.active');
    }

    /** Active periods running today in Qatar. Cached briefly; the list is tiny. */
    public static function current(?CarbonImmutable $at = null): Collection
    {
        $today = ($at ?? CarbonImmutable::now(Business::TIMEZONE))->setTimezone(Business::TIMEZONE)->toDateString();
        $all = Cache::remember('seasonal.active', 600, fn () => self::query()->where('is_active', true)
            ->where('ends_on', '>=', CarbonImmutable::now(Business::TIMEZONE)->subDays(2)->toDateString())
            ->orderBy('starts_on')->get());

        return $all->filter(fn (self $p) => $p->starts_on->toDateString() <= $today && $p->ends_on->toDateString() >= $today)->values();
    }

    /** Periods an owner can prepare for: running now or starting within the next 60 days. */
    public function scopeUpcoming(Builder $q): Builder
    {
        $today = CarbonImmutable::now(Business::TIMEZONE);

        return $q->where('is_active', true)->where('ends_on', '>=', $today->toDateString())
            ->where('starts_on', '<=', $today->addDays(60)->toDateString())->orderBy('starts_on');
    }

    public function isRunning(?CarbonImmutable $at = null): bool
    {
        $today = ($at ?? CarbonImmutable::now(Business::TIMEZONE))->toDateString();

        return $this->is_active && $this->starts_on->toDateString() <= $today && $this->ends_on->toDateString() >= $today;
    }

    public function dateRange(): string
    {
        $from = $this->starts_on->locale(app()->getLocale());
        $to = $this->ends_on->locale(app()->getLocale());

        return $from->equalTo($to) ? $from->isoFormat('D MMMM YYYY') : $from->isoFormat('D MMM').' – '.$to->isoFormat('D MMM YYYY');
    }
}
