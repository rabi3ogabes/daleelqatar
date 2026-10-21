<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\FlushesDirectoryCaches;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Municipality;
use App\Services\AuditLogger;
use App\Services\SlugGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AreaController extends Controller
{
    use FlushesDirectoryCaches;

    public function __construct(private readonly AuditLogger $audit) {}

    public function create(Municipality $municipality): View
    {
        return view('admin.locations.area-form', [
            'area' => new Area(['municipality_id' => $municipality->id, 'is_active' => true]),
            'municipality' => $municipality,
            'municipalities' => Municipality::query()->ordered()->get(),
        ]);
    }

    public function store(Request $request, Municipality $municipality, SlugGenerator $slugs): RedirectResponse
    {
        $data = $this->validated($request, $municipality);
        $area = new Area($data);
        $area->slug = ($data['slug'] ?? null) ?: $slugs->unique(Area::class, $data['name_en']);
        $area->sort_order = $data['sort_order'] ?? ((int) Area::query()->where('municipality_id', $data['municipality_id'])->max('sort_order') + 1);
        $area->save();
        $this->audit->log('area.created', $area, [], $area->only(['name_ar', 'name_en', 'slug', 'municipality_id']));
        $this->flushDirectoryCaches();

        return redirect()->route('admin.municipalities.index')->with('success', __('admin.locations.area_created'));
    }

    public function edit(Area $area): View
    {
        return view('admin.locations.area-form', [
            'area' => $area,
            'municipality' => $area->municipality,
            'municipalities' => Municipality::query()->ordered()->get(),
        ]);
    }

    public function update(Request $request, Area $area): RedirectResponse
    {
        $data = $this->validated($request, $area->municipality, $area);
        if ((int) $data['municipality_id'] !== $area->municipality_id && $area->businesses()->withTrashed()->exists()) {
            return back()->withInput()->with('error', __('admin.locations.area_move_blocked'));
        }
        $original = $area->getAttributes();
        $area->fill($data);
        if (! empty($data['slug'])) {
            $area->slug = $data['slug'];
        }
        $area->save();
        $this->audit->logChanges('area.updated', $area, $original);
        $this->flushDirectoryCaches();

        return redirect()->route('admin.municipalities.index')->with('success', __('common.updated'));
    }

    public function destroy(Area $area): RedirectResponse
    {
        if ($area->businesses()->withTrashed()->exists()) {
            return back()->with('error', __('admin.locations.in_use'));
        }
        $this->audit->log('area.deleted', $area, $area->only(['name_ar', 'name_en', 'slug']));
        $area->delete();
        $this->flushDirectoryCaches();

        return back()->with('success', __('common.deleted'));
    }

    private function validated(Request $request, Municipality $municipality, ?Area $area = null): array
    {
        $data = $request->validate([
            'municipality_id' => ['required', 'integer', Rule::exists('municipalities', 'id')],
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['required', 'string', 'max:120', Rule::unique('areas', 'name_en')->where('municipality_id', $request->integer('municipality_id'))->ignore($area?->id)],
            'slug' => ['nullable', 'string', 'max:140', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('areas', 'slug')->ignore($area?->id)],
            'latitude' => ['nullable', 'numeric', 'between:24.3,26.3'],
            'longitude' => ['nullable', 'numeric', 'between:50.6,51.8'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['is_featured'] = $request->boolean('is_featured');

        return $data;
    }
}
