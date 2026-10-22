<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\SeasonalPeriod;
use App\Support\Seasons\SpecialHoursService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The owner sets Ramadan, Eid and holiday hours for their listing. */
class SpecialHoursController extends Controller
{
    public function edit(Request $request, Business $business): View
    {
        abort_unless($business->isOwnedBy($request->user()), 404);

        return view('account.special-hours', self::viewData($business) + [
            'formRoute' => fn (SeasonalPeriod $p) => route('account.businesses.special-hours.update', [$business->uuid, $p->id]),
            'layout' => 'account',
            'back' => route('account.businesses.show', $business->uuid),
        ]);
    }

    public function update(Request $request, Business $business, int $period, SpecialHoursService $service): RedirectResponse
    {
        abort_unless($business->isOwnedBy($request->user()), 404);
        $season = self::season($period);
        $data = self::validated($request);

        $service->save($business, $season, $data['mode'], (array) ($data['hours'] ?? []), $request->user()->id);

        return redirect()->route('account.businesses.special-hours', $business->uuid)->with('success', __('seasons.owner.saved', ['season' => $season->t('name')]));
    }

    /** Shared with the admin screen. */
    public static function viewData(Business $business): array
    {
        $periods = SeasonalPeriod::query()->upcoming()->get();
        $business->load(['openingHours', 'specialHours' => fn ($q) => $q->whereIn('seasonal_period_id', $periods->pluck('id'))]);
        $regular = SpecialHoursService::editorState(collect(), $business->openingHours);

        return [
            'business' => $business,
            'periods' => $periods->map(fn (SeasonalPeriod $p) => [
                'period' => $p,
                'mode' => SpecialHoursService::mode($own = $business->specialHours->where('seasonal_period_id', $p->id)->values()),
                'state' => SpecialHoursService::editorState($own, $business->openingHours, $p->kind),
            ]),
            'regular' => $regular,
            'hasRegular' => $business->openingHours->isNotEmpty(),
        ];
    }

    public static function season(int $id): SeasonalPeriod
    {
        return SeasonalPeriod::query()->upcoming()->findOrFail($id);
    }

    public static function validated(Request $request): array
    {
        $time = ['nullable', 'date_format:H:i'];

        return $request->validate([
            'mode' => ['required', Rule::in(['regular', 'custom', 'closed'])],
            'hours' => ['nullable', 'array'],
            'hours.*.closed' => ['nullable', 'boolean'],
            'hours.*.all_day' => ['nullable', 'boolean'],
            'hours.*.periods' => ['nullable', 'array', 'max:2'],
            'hours.*.periods.*.opens' => $time,
            'hours.*.periods.*.closes' => $time,
        ]);
    }
}
