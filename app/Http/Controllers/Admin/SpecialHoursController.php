<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Account\SpecialHoursController as OwnerSpecialHours;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\SeasonalPeriod;
use App\Support\Seasons\SpecialHoursService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Staff set seasonal hours on any listing (e.g. for businesses without an owner yet). */
class SpecialHoursController extends Controller
{
    public function edit(Business $business): View
    {
        return view('account.special-hours', OwnerSpecialHours::viewData($business) + [
            'formRoute' => fn (SeasonalPeriod $p) => route('admin.businesses.special-hours.update', [$business->id, $p->id]),
            'layout' => 'admin',
            'back' => route('admin.businesses.show', $business->id),
        ]);
    }

    public function update(Request $request, Business $business, int $period, SpecialHoursService $service): RedirectResponse
    {
        $season = OwnerSpecialHours::season($period);
        $data = OwnerSpecialHours::validated($request);

        $service->save($business, $season, $data['mode'], (array) ($data['hours'] ?? []), $request->user()->id);

        return redirect()->route('admin.businesses.special-hours', $business->id)->with('success', __('seasons.owner.saved', ['season' => $season->t('name')]));
    }
}
