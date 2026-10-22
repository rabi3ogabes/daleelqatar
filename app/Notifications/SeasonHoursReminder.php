<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Business;
use App\Models\SeasonalPeriod;

/** A week before Ramadan or a holiday: "set your special hours". */
class SeasonHoursReminder extends DirectoryNotification
{
    public function __construct(public readonly SeasonalPeriod $season, public readonly Business $business) {}

    protected function type(): string
    {
        return 'season.reminder';
    }

    protected function title(): string
    {
        return __('seasons.reminder.title', ['season' => $this->season->t('name')]);
    }

    protected function body(): string
    {
        return __('seasons.reminder.body', ['season' => $this->season->t('name'), 'date' => format_date($this->season->starts_on), 'business' => $this->business->t('name')]);
    }

    protected function link(): ?array
    {
        return ['account.businesses.special-hours', [$this->business->uuid]];
    }

    protected function icon(): string
    {
        return 'moon';
    }
}
