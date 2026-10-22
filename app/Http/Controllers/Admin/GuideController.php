<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Business;
use App\Models\Category;
use App\Models\Guide;
use App\Models\Municipality;
use App\Services\AuditLogger;
use App\Services\SlugGenerator;
use App\Support\Guides\GuideBuilder;
use App\Support\NavigationData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Editors build guides from criteria, preview the listings live, and pin their picks. */
class GuideController extends Controller
{
    public function index(GuideBuilder $builder): View
    {
        $guides = Guide::query()->with(['category:id,name_ar,name_en', 'municipality:id,name_ar,name_en', 'area:id,name_ar,name_en'])
            ->latest('updated_at')->get();

        return view('admin.guides.index', [
            'guides' => $guides,
            'counts' => $guides->mapWithKeys(fn (Guide $g) => [$g->id => $builder->count($builder->criteria($g)) + $g->pinned()->count()]),
            'suggestions' => count($builder->suggestions(1)) > 0,
        ]);
    }

    public function suggestions(GuideBuilder $builder): View
    {
        return view('admin.guides.suggestions', ['suggestions' => $builder->suggestions()]);
    }

    public function create(Request $request, GuideBuilder $builder, NavigationData $nav): View
    {
        $guide = new Guide(['max_items' => 12, 'open_24h' => $request->boolean('open_24h')]);
        $category = Category::query()->find((int) $request->query('category_id'));
        $municipality = Municipality::query()->find((int) $request->query('municipality_id'));
        if ($category || $municipality || $guide->open_24h) {
            $guide->fill(['category_id' => $category?->id, 'municipality_id' => $municipality?->id]);
            $guide->fill($builder->draft($category, $municipality, null, $guide->open_24h, $builder->count($builder->criteria($guide))));
        }

        return view('admin.guides.form', $this->formData($guide, $nav));
    }

    public function store(Request $request, SlugGenerator $slugs, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $guide = new Guide($data['fields']);
        $guide->slug = $slugs->unique(Guide::class, $data['fields']['title_en'] ?: $data['fields']['title_ar']);
        $this->applyStatus($guide, $data['status']);
        $guide->updated_by = $request->user()->id;
        DB::transaction(function () use ($guide, $data) {
            $guide->save();
            $this->syncPinned($guide, $data['pinned']);
        });
        $audit->log('guide.created', $guide, [], $data['fields']);

        return redirect()->route('admin.guides.edit', $guide)->with('success', __('guides.admin.saved'));
    }

    public function edit(Guide $guide, NavigationData $nav): View
    {
        return view('admin.guides.form', $this->formData($guide->load('pinned:id,name_ar,name_en,slug'), $nav));
    }

    public function update(Request $request, Guide $guide, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request, $guide);
        $old = $guide->only(array_keys($data['fields']));
        $guide->fill($data['fields']);
        $this->applyStatus($guide, $data['status']);
        $guide->updated_by = $request->user()->id;
        DB::transaction(function () use ($guide, $data) {
            $guide->save();
            $this->syncPinned($guide, $data['pinned']);
        });
        $audit->log('guide.updated', $guide, $old, $data['fields']);

        return redirect()->route('admin.guides.edit', $guide)->with('success', __('guides.admin.saved'));
    }

    public function destroy(Guide $guide, AuditLogger $audit): RedirectResponse
    {
        $audit->log('guide.deleted', $guide, $guide->only(['slug', 'title_en', 'title_ar']));
        $guide->delete();

        return redirect()->route('admin.guides.index')->with('success', __('guides.admin.deleted'));
    }

    /** Live preview while editing: how many listings qualify, and the top ones (to pin). */
    public function preview(Request $request, GuideBuilder $builder): JsonResponse
    {
        $criteria = [
            'category_id' => (int) $request->query('category_id') ?: null,
            'municipality_id' => (int) $request->query('municipality_id') ?: null,
            'area_id' => (int) $request->query('area_id') ?: null,
            'open_24h' => $request->boolean('open_24h'),
            'verified_only' => $request->boolean('verified_only'),
        ];
        $guide = new Guide($criteria + ['max_items' => 40]);
        $top = $builder->listings($guide);

        return response()->json([
            'count' => $builder->count($criteria),
            'items' => $top->map(fn (Business $b) => [
                'id' => $b->id,
                'name' => $b->t('name'),
                'meta' => trim(($b->category?->t('name') ?? '').' · '.($b->locationLabel() ?? ''), ' ·'),
                'verified' => (bool) $b->is_verified,
            ])->values(),
            'min' => GuideBuilder::MIN_LISTINGS,
        ]);
    }

    private function formData(Guide $guide, NavigationData $nav): array
    {
        return [
            'guide' => $guide,
            'categories' => $nav->rootCategories(),
            'municipalities' => $nav->municipalities(),
            'areaOptions' => $nav->areaOptions(),
            'pinned' => $guide->exists ? $guide->pinned->map(fn ($b) => ['id' => $b->id, 'name' => $b->t('name')])->values()->all() : [],
        ];
    }

    private function validated(Request $request, ?Guide $guide = null): array
    {
        $data = $request->validate([
            'title_ar' => ['required', 'string', 'max:160'],
            'title_en' => ['required', 'string', 'max:160'],
            'intro_ar' => ['nullable', 'string', 'max:4000'],
            'intro_en' => ['nullable', 'string', 'max:4000'],
            'meta_description_ar' => ['nullable', 'string', 'max:300'],
            'meta_description_en' => ['nullable', 'string', 'max:300'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'municipality_id' => ['nullable', 'integer', Rule::exists('municipalities', 'id')],
            'area_id' => ['nullable', 'integer', Rule::exists('areas', 'id')],
            'max_items' => ['required', 'integer', 'min:3', 'max:30'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'pinned' => ['nullable', 'array', 'max:30'],
            'pinned.*' => ['integer'],
        ]);
        $fields = collect($data)->only(['title_ar', 'title_en', 'intro_ar', 'intro_en', 'meta_description_ar', 'meta_description_en', 'category_id', 'municipality_id', 'area_id', 'max_items'])->all();
        $fields['open_24h'] = $request->boolean('open_24h');
        $fields['verified_only'] = $request->boolean('verified_only');
        if (! empty($fields['area_id']) && ($area = Area::query()->find($fields['area_id']))) {
            $fields['municipality_id'] = $area->municipality_id;
        }

        return ['fields' => $fields, 'status' => $data['status'], 'pinned' => array_values(array_unique(array_map('intval', $data['pinned'] ?? [])))];
    }

    private function applyStatus(Guide $guide, string $status): void
    {
        $guide->status = $status;
        if ($status === 'published' && ! $guide->published_at) {
            $guide->published_at = now();
        }
    }

    private function syncPinned(Guide $guide, array $ids): void
    {
        $valid = Business::query()->published()->whereIn('id', $ids)->pluck('id')->all();
        $sync = [];
        foreach ($ids as $i => $id) {
            if (in_array($id, $valid, true)) {
                $sync[$id] = ['sort_order' => $i];
            }
        }
        $guide->pinned()->sync($sync);
    }
}
