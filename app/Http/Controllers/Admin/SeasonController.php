<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeasonalPeriod;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Ramadan, Eid, National Day… the dates when owners can publish special hours. */
class SeasonController extends Controller
{
    public function index(): View
    {
        $seasons = SeasonalPeriod::query()->orderByDesc('starts_on')->get();
        $counts = DB::table('special_hours')->selectRaw('seasonal_period_id, COUNT(DISTINCT business_id) AS n')
            ->groupBy('seasonal_period_id')->pluck('n', 'seasonal_period_id');

        return view('admin.seasons.index', ['seasons' => $seasons, 'counts' => $counts]);
    }

    public function create(): View
    {
        return view('admin.seasons.form', ['season' => new SeasonalPeriod(['kind' => 'ramadan', 'is_active' => true])]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $season = new SeasonalPeriod($data);
        $season->slug = $this->slug($data);
        $season->save();
        $audit->log('season.saved', $season, [], $data);

        return redirect()->route('admin.seasons.index')->with('success', __('seasons.admin.saved'));
    }

    public function edit(SeasonalPeriod $season): View
    {
        return view('admin.seasons.form', ['season' => $season]);
    }

    public function update(Request $request, SeasonalPeriod $season, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $old = $season->only(array_keys($data));
        $season->fill($data);
        // New dates mean owners deserve a new reminder.
        if ($season->isDirty('starts_on')) {
            $season->reminded_at = null;
        }
        $season->save();
        $audit->log('season.saved', $season, $old, $data);

        return redirect()->route('admin.seasons.index')->with('success', __('seasons.admin.saved'));
    }

    public function destroy(SeasonalPeriod $season, AuditLogger $audit): RedirectResponse
    {
        $audit->log('season.deleted', $season, $season->only(['slug', 'starts_on', 'ends_on']));
        $season->delete();

        return redirect()->route('admin.seasons.index')->with('success', __('seasons.admin.deleted'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in(SeasonalPeriod::KINDS)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function slug(array $data): string
    {
        $base = Str::slug($data['name_en']) ?: $data['kind'];
        $slug = $base;
        $i = 2;
        while (SeasonalPeriod::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
