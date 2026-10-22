<?php

declare(strict_types=1);

namespace App\Support\Insights;

use App\Enums\BusinessEventType;
use App\Enums\ModerationStatus;
use App\Enums\QuoteRecipientStatus;
use App\Models\Business;
use App\Models\SeasonalPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Performance of one listing over a period, compared with the period before
 * and with the average listing in its category. Every number is counted from
 * recorded events; nothing is estimated.
 */
final class BusinessInsights
{
    public const PERIODS = ['30d', 'month', 'last_month', '90d'];

    /** Metric => [icon, event types counted]. Order is the display order. */
    public const METRICS = [
        'views' => ['eye', [BusinessEventType::View]],
        'impressions' => ['search', []],
        'calls' => ['phone', [BusinessEventType::Call, BusinessEventType::PhoneReveal]],
        'whatsapp' => ['whatsapp', [BusinessEventType::WhatsApp]],
        'directions' => ['navigation', [BusinessEventType::Directions]],
        'website' => ['globe', [BusinessEventType::Website]],
        'shares' => ['share-2', [BusinessEventType::Share]],
        'qr_scans' => ['qr-code', []],
        'favorites' => ['heart', []],
        'quotes' => ['message-square-quote', []],
        'reviews' => ['star', []],
    ];

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: CarbonImmutable, 3: CarbonImmutable} [from, to, prevFrom, prevTo] (to exclusive) */
    public static function range(string $period, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $today = $now->startOfDay();

        return match ($period) {
            'month' => [$now->startOfMonth(), $today->addDay(), $now->startOfMonth()->subMonth(), $now->startOfMonth()->subMonth()->addDays($today->day)],
            'last_month' => [$now->startOfMonth()->subMonth(), $now->startOfMonth(), $now->startOfMonth()->subMonths(2), $now->startOfMonth()->subMonth()],
            '90d' => [$today->subDays(89), $today->addDay(), $today->subDays(179), $today->subDays(89)],
            default => [$today->subDays(29), $today->addDay(), $today->subDays(59), $today->subDays(29)],
        };
    }

    /** @return array<string, int> metric => total for one business */
    public function totals(Business $business, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $events = DB::table('business_events')->where('business_id', $business->id)
            ->where('created_at', '>=', $from)->where('created_at', '<', $to)
            ->selectRaw('type, COUNT(*) AS n')->groupBy('type')->pluck('n', 'type');
        $daily = DB::table('business_daily_stats')->where('business_id', $business->id)
            ->where('day', '>=', $from->toDateString())->where('day', '<', $to->toDateString())
            ->selectRaw('COALESCE(SUM(impressions), 0) AS impressions, COALESCE(SUM(qr_scans), 0) AS qr_scans')->first();

        $out = [];
        foreach (self::METRICS as $metric => [, $types]) {
            $out[$metric] = (int) collect($types)->sum(fn (BusinessEventType $t) => (int) ($events[$t->value] ?? 0));
        }
        $out['impressions'] = (int) $daily->impressions;
        $out['qr_scans'] = (int) $daily->qr_scans;
        $out['favorites'] = DB::table('favorites')->where('business_id', $business->id)->where('created_at', '>=', $from)->where('created_at', '<', $to)->count();
        $out['quotes'] = DB::table('quote_request_recipients')->where('business_id', $business->id)->where('created_at', '>=', $from)->where('created_at', '<', $to)->count();
        $out['reviews'] = DB::table('reviews')->where('business_id', $business->id)->whereNull('deleted_at')->where('status', ModerationStatus::Approved->value)
            ->where('created_at', '>=', $from)->where('created_at', '<', $to)->count();

        return $out;
    }

    /** Contact actions: what turns a view into a customer. */
    public static function contacts(array $totals): int
    {
        return $totals['calls'] + $totals['whatsapp'] + $totals['directions'] + $totals['website'];
    }

    /**
     * Average views and contacts of published listings in the same category, or null when the
     * category is too small for a fair comparison.
     *
     * @return array{views: float, contacts: float, listings: int}|null
     */
    public function categoryAverage(Business $business, CarbonImmutable $from, CarbonImmutable $to): ?array
    {
        if (! $business->category_id) {
            return null;
        }
        $listings = Business::query()->published()->where('category_id', $business->category_id)->count();
        if ($listings < 5) {
            return null;
        }
        $contactTypes = array_map(fn ($t) => $t->value, [BusinessEventType::Call, BusinessEventType::PhoneReveal, BusinessEventType::WhatsApp, BusinessEventType::Directions, BusinessEventType::Website]);
        $row = DB::table('business_events')
            ->join('businesses', 'businesses.id', '=', 'business_events.business_id')
            ->where('businesses.category_id', $business->category_id)->whereNull('businesses.deleted_at')
            ->where('business_events.created_at', '>=', $from)->where('business_events.created_at', '<', $to)
            ->selectRaw("SUM(CASE WHEN business_events.type = 'view' THEN 1 ELSE 0 END) AS views")
            ->selectRaw('SUM(CASE WHEN business_events.type IN ('.implode(',', array_fill(0, count($contactTypes), '?')).') THEN 1 ELSE 0 END) AS contacts', $contactTypes)
            ->first();

        return ['views' => (int) $row->views / $listings, 'contacts' => (int) $row->contacts / $listings, 'listings' => $listings];
    }

    /** @return array{labels: list<string>, views: list<int>, impressions: list<int>} */
    public function daily(Business $business, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $views = DB::table('business_events')->where('business_id', $business->id)->where('type', BusinessEventType::View->value)
            ->where('created_at', '>=', $from)->where('created_at', '<', $to)
            ->selectRaw("to_char(created_at, 'YYYY-MM-DD') AS d, COUNT(*) AS n")->groupBy('d')->pluck('n', 'd');
        $impressions = DB::table('business_daily_stats')->where('business_id', $business->id)
            ->where('day', '>=', $from->toDateString())->where('day', '<', $to->toDateString())
            ->selectRaw("to_char(day, 'YYYY-MM-DD') AS d, impressions AS n")->pluck('n', 'd');

        $out = ['labels' => [], 'views' => [], 'impressions' => []];
        for ($d = $from; $d->lt($to); $d = $d->addDay()) {
            $key = $d->toDateString();
            $out['labels'][] = $d->locale(app()->getLocale())->isoFormat('D MMM');
            $out['views'][] = (int) ($views[$key] ?? 0);
            $out['impressions'][] = (int) ($impressions[$key] ?? 0);
        }

        return $out;
    }

    public static function change(int $current, int $previous): ?int
    {
        if ($previous === 0) {
            return null;
        }

        return (int) round(($current - $previous) / $previous * 100);
    }

    /**
     * Concrete next steps, most valuable first, each with where to do it.
     *
     * @return list<array{key: string, icon: string, route: string, params: array}>
     */
    public function tips(Business $business, array $totals): array
    {
        $tips = [];
        $edit = ['account.businesses.edit', [$business->uuid]];
        $openLeads = DB::table('quote_request_recipients')->join('quote_requests', 'quote_requests.id', '=', 'quote_request_recipients.quote_request_id')
            ->where('quote_request_recipients.business_id', $business->id)->whereIn('quote_request_recipients.status', [QuoteRecipientStatus::Sent->value, QuoteRecipientStatus::Viewed->value])
            ->where('quote_requests.status', 'open')->where('quote_requests.expires_at', '>', now())->count();
        if ($openLeads > 0) {
            $tips[] = ['key' => 'leads', 'icon' => 'message-square-quote', 'route' => 'account.leads.index', 'params' => [], 'count' => $openLeads];
        }
        $unanswered = DB::table('reviews')->where('business_id', $business->id)->whereNull('deleted_at')->where('status', ModerationStatus::Approved->value)->whereNull('owner_reply')->count();
        if ($unanswered > 0) {
            $tips[] = ['key' => 'reviews', 'icon' => 'star', 'route' => 'account.reviews.index', 'params' => [], 'count' => $unanswered];
        }
        $season = SeasonalPeriod::query()->upcoming()->first();
        if ($season && ! $business->specialHours()->where('seasonal_period_id', $season->id)->exists()) {
            $tips[] = ['key' => 'season', 'icon' => 'moon', 'route' => 'account.businesses.special-hours', 'params' => [$business->uuid], 'season' => $season->t('name')];
        }
        if (! $business->cover_path && $business->images()->doesntExist()) {
            $tips[] = ['key' => 'photos', 'icon' => 'image', 'route' => $edit[0], 'params' => $edit[1]];
        }
        if ($business->openingHours()->doesntExist()) {
            $tips[] = ['key' => 'hours', 'icon' => 'clock', 'route' => $edit[0], 'params' => $edit[1]];
        }
        if (mb_strlen(trim((string) $business->t('description'))) < 120) {
            $tips[] = ['key' => 'description', 'icon' => 'file-text', 'route' => $edit[0], 'params' => $edit[1]];
        }
        if (! $business->whatsapp) {
            $tips[] = ['key' => 'whatsapp', 'icon' => 'whatsapp', 'route' => $edit[0], 'params' => $edit[1]];
        }
        if ($totals['qr_scans'] === 0) {
            $tips[] = ['key' => 'poster', 'icon' => 'qr-code', 'route' => 'account.businesses.poster', 'params' => [$business->uuid]];
        }
        if (! $business->is_verified) {
            $tips[] = ['key' => 'verify', 'icon' => 'badge-check', 'route' => 'account.businesses.verification', 'params' => [$business->uuid]];
        }

        return array_slice($tips, 0, 5);
    }
}
