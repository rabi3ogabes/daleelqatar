<?php

declare(strict_types=1);

namespace App\Support\Seasons;

use App\Models\Business;
use App\Models\SeasonalPeriod;
use App\Services\AuditLogger;
use App\Services\BusinessManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Saving a business's hours for one season, and the editor state for the form. */
final class SpecialHoursService
{
    public const DAYS = [6, 0, 1, 2, 3, 4, 5]; // Saturday first, as most weeks in Qatar

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  'custom'|'closed'|'regular'  $mode  custom hours, closed for the whole season, or no special hours
     * @param  array  $hours  same shape as the listing form: [day => [closed, all_day, periods[]]]
     */
    public function save(Business $business, SeasonalPeriod $period, string $mode, array $hours, ?int $userId = null): void
    {
        DB::transaction(function () use ($business, $period, $mode, $hours) {
            $business->specialHours()->where('seasonal_period_id', $period->id)->delete();

            $rows = match ($mode) {
                'closed' => array_map(fn ($d) => ['day_of_week' => $d, 'is_closed' => true, 'opens_at' => null, 'closes_at' => null], self::DAYS),
                'custom' => BusinessManager::hoursRows($hours),
                default => [],
            };
            foreach ($rows as $row) {
                $business->specialHours()->create($row + ['seasonal_period_id' => $period->id]);
            }
        });
        $business->unsetRelation('specialHours');
        $this->audit->log('special_hours.updated', $business, [], ['period' => $period->slug, 'mode' => $mode], [], $userId);
    }

    /** Editor rows for the hours component: the season's hours, else a copy of the regular week to start from. */
    public static function editorState(Collection $rows, Collection $fallback, string $kind = 'other'): array
    {
        $source = $rows->isNotEmpty() ? $rows : $fallback;
        $byDay = $source->groupBy('day_of_week');

        $default = $kind === 'ramadan' ? ['opens' => '20:00', 'closes' => '02:00'] : ['opens' => '09:00', 'closes' => '22:00'];

        return collect(self::DAYS)->map(function ($d) use ($byDay, $default) {
            $dayRows = $byDay->get($d, collect());
            $closed = $dayRows->isNotEmpty() && $dayRows->first()->is_closed;
            $allDay = $dayRows->isNotEmpty() && $dayRows->first()->isAllDay();
            $periods = $dayRows->filter(fn ($r) => ! $r->is_closed && ! $r->isAllDay())
                ->map(fn ($r) => ['opens' => substr((string) $r->opens_at, 0, 5), 'closes' => substr((string) $r->closes_at, 0, 5)])->values()->all();

            return [
                'day' => $d,
                'label' => __('business.days.'.$d),
                'enabled' => $dayRows->isNotEmpty(),
                'closed' => $closed,
                'all_day' => $allDay,
                'periods' => $periods ?: [$default],
            ];
        })->values()->all();
    }

    /** custom | closed | regular, as currently saved for this season. */
    public static function mode(Collection $rows): string
    {
        if ($rows->isEmpty()) {
            return 'regular';
        }

        return $rows->count() === 7 && $rows->every(fn ($r) => $r->is_closed) ? 'closed' : 'custom';
    }
}
