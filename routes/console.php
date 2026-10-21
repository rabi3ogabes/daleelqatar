<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks — run `php artisan schedule:work` (or a cron calling
| `php artisan schedule:run` every minute) in production.
|--------------------------------------------------------------------------
*/

// Keep the XML sitemap warm for crawlers.
Schedule::command('daleel:sitemap')->hourly()->withoutOverlapping();

// Housekeeping
Schedule::command('queue:prune-failed --hours=720')->daily();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('auth:clear-resets')->everyFifteenMinutes();
Schedule::command('model:prune')->daily();
