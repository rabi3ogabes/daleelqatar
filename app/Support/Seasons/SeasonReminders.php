<?php

declare(strict_types=1);

namespace App\Support\Seasons;

use App\Models\Business;
use App\Models\SeasonalPeriod;
use App\Notifications\SeasonHoursReminder;
use Throwable;

/** Reminds owners, once per season and about a week ahead, to set their special hours. */
final class SeasonReminders
{
    public const DAYS_AHEAD = 7;

    public function send(int $limit = 300): int
    {
        $today = now()->startOfDay();
        $season = SeasonalPeriod::query()->where('is_active', true)->whereNull('reminded_at')
            ->whereBetween('starts_on', [$today->toDateString(), $today->copy()->addDays(self::DAYS_AHEAD)->toDateString()])
            ->orderBy('starts_on')->first();
        if (! $season) {
            return 0;
        }

        $sent = 0;
        $businesses = Business::query()->published()->whereNotNull('owner_id')
            ->whereDoesntHave('specialHours', fn ($q) => $q->where('seasonal_period_id', $season->id))
            ->with('owner')->orderBy('id')->limit($limit)->get();
        foreach ($businesses as $business) {
            try {
                $business->owner?->notify(new SeasonHoursReminder($season, $business));
                $sent++;
            } catch (Throwable $e) {
                report($e);
            }
        }
        $season->forceFill(['reminded_at' => now()])->save();

        return $sent;
    }
}
