<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserStatus;
use App\Models\Business;
use App\Models\User;
use App\Notifications\MonthlyInsightsReport;
use App\Support\Insights\BusinessInsights;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sends last month's results to every owner of a published listing, once per
 * month. Safe to run every day: an owner who already received this month's
 * report is skipped, so a run cut short simply continues the next day.
 */
class SendInsightsReports extends Command
{
    protected $signature = 'daleel:insights-report {--limit=200 : Owners per run} {--seconds=30 : Time budget}';

    protected $description = 'E-mail business owners their monthly listing performance';

    public function handle(BusinessInsights $insights): int
    {
        if (! setting('insights.monthly_email', true)) {
            $this->info('Monthly report is turned off.');

            return self::SUCCESS;
        }

        $started = microtime(true);
        [$from, $to, $prevFrom, $prevTo] = BusinessInsights::range('last_month');
        $period = $from->format('Y-m');
        $sent = 0;

        $owners = User::query()
            ->where('status', UserStatus::Active->value)
            ->whereNotNull('email_verified_at')
            ->whereHas('businesses', fn ($q) => $q->published())
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('insight_reports')->whereColumn('insight_reports.user_id', 'users.id')->where('period', $period))
            ->orderBy('id')->limit((int) $this->option('limit'))->get();

        foreach ($owners as $owner) {
            if (microtime(true) - $started > (float) $this->option('seconds')) {
                break;
            }
            // Claim the month first, so two overlapping runs never send twice.
            if (! DB::table('insight_reports')->insertOrIgnore(['user_id' => $owner->id, 'period' => $period, 'sent_at' => now()])) {
                continue;
            }
            if (! $owner->wantsNotification('email') || ! $owner->wantsNotification('monthly_report')) {
                continue;
            }

            try {
                $owner->notify(new MonthlyInsightsReport(
                    $this->monthName($from, $owner->preferredLocale()),
                    $this->lines($owner, $insights, $from, $to, $prevFrom, $prevTo),
                ));
                $sent++;
            } catch (Throwable $e) {
                // Let tomorrow's run try again.
                DB::table('insight_reports')->where('user_id', $owner->id)->where('period', $period)->delete();
                report($e);
            }
        }

        $this->info("Sent {$sent} monthly reports for {$period}.");

        return self::SUCCESS;
    }

    private function lines(User $owner, BusinessInsights $insights, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $prevFrom, CarbonImmutable $prevTo): array
    {
        $locale = $owner->preferredLocale();

        return $owner->businesses()->published()->orderBy('id')->limit(10)->get()
            ->map(function (Business $b) use ($insights, $from, $to, $prevFrom, $prevTo, $locale) {
                $current = $insights->totals($b, $from, $to);
                $previous = $insights->totals($b, $prevFrom, $prevTo);
                $tip = $insights->tips($b, $current)[0] ?? null;

                return [
                    'name' => $b->t('name', $locale),
                    'uuid' => $b->uuid,
                    'views' => $current['views'],
                    'views_change' => BusinessInsights::change($current['views'], $previous['views']),
                    'impressions' => $current['impressions'],
                    'contacts' => BusinessInsights::contacts($current),
                    'quotes' => $current['quotes'],
                    'tip' => $tip ? trans_choice('insights.tip.'.$tip['key'].'_title', $tip['count'] ?? 1, ['count' => $tip['count'] ?? 0, 'season' => $tip['season'] ?? ''], $locale) : null,
                ];
            })->all();
    }

    private function monthName(CarbonImmutable $month, string $locale): string
    {
        return $month->locale($locale)->isoFormat('MMMM YYYY');
    }
}
