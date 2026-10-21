<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\FlushesDirectoryCaches;
use App\Http\Controllers\Controller;
use App\Models\Municipality;
use App\Services\AuditLogger;
use App\Services\SlugGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MunicipalityController extends Controller
{
    use FlushesDirectoryCaches;

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        $municipalities = Municipality::query()->ordered()
            ->with(['areas' => fn ($q) => $q->withCount('businesses')])
            ->withCount(['businesses', 'areas'])->get();

        return view('admin.locations.index', ['municipalities' => $municipalities]);
    }

    public function create(): View
    {
        return view('admin.locations.municipality-form', ['municipality' => new Municipality(['is_active' => true])]);
    }

    public function store(Request $request, SlugGenerator $slugs): RedirectResponse
    {
        $data = $this->validated($request);
        $m = new Municipality($data);
        $m->slug = ($data['slug'] ?? null) ?: $slugs->unique(Municipality::class, $data['name_en']);
        $m->sort_order = $data['sort_order'] ?? ((int) Municipality::query()->max('sort_order') + 1);
        $m->save();
        $this->audit->log('municipality.created', $m, [], $m->only(['name_ar', 'name_en', 'slug']));
        $this->flushDirectoryCaches();

        return redirect()->route('admin.municipalities.index')->with('success', __('admin.locations.created'));
    }

    public function edit(Municipality $municipality): View
    {
        return view('admin.locations.municipality-form', ['municipality' => $municipality]);
    }

    public function update(Request $request, Municipality $municipality): RedirectResponse
    {
        $data = $this->validated($request, $municipality);
        $original = $municipality->getAttributes();
        $municipality->fill($data);
        if (! empty($data['slug'])) {
            $municipality->slug = $data['slug'];
        }
        $municipality->save();
        $this->audit->logChanges('municipality.updated', $municipality, $original);
        $this->flushDirectoryCaches();

        return redirect()->route('admin.municipalities.index')->with('success', __('common.updated'));
    }

    public function destroy(Municipality $municipality): RedirectResponse
    {
        if ($municipality->businesses()->withTrashed()->exists() || $municipality->areas()->exists()) {
            return back()->with('error', __('admin.locations.in_use'));
        }
        $this->audit->log('municipality.deleted', $municipality, $municipality->only(['name_ar', 'name_en', 'slug']));
        $municipality->delete();
        $this->flushDirectoryCaches();

        return back()->with('success', __('common.deleted'));
    }

    private function validated(Request $request, ?Municipality $m = null): array
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('municipalities', 'slug')->ignore($m?->id)],
            'description_ar' => ['nullable', 'string', 'max:2000'],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:24.3,26.3'],
            'longitude' => ['nullable', 'numeric', 'between:50.6,51.8'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['is_featured'] = $request->boolean('is_featured');

        return $data;
    }
}
