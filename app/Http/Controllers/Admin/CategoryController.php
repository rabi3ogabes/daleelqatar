<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\FlushesDirectoryCaches;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\AuditLogger;
use App\Services\MediaService;
use App\Services\SlugGenerator;
use App\Support\Icons;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    use FlushesDirectoryCaches;

    public function __construct(private readonly AuditLogger $audit, private readonly MediaService $media) {}

    public function index(): View
    {
        $roots = Category::query()->roots()->ordered()
            ->with(['children' => fn ($q) => $q->withCount('businesses')])
            ->withCount(['businesses', 'children'])->get();

        return view('admin.categories.index', ['roots' => $roots]);
    }

    public function create(Request $request): View
    {
        return view('admin.categories.form', [
            'category' => new Category(['parent_id' => $request->integer('parent') ?: null, 'is_active' => true, 'icon' => 'layout-grid']),
            'parents' => Category::query()->roots()->ordered()->get(),
            'icons' => array_keys(Icons::PATHS),
        ]);
    }

    public function store(Request $request, SlugGenerator $slugs): RedirectResponse
    {
        $data = $this->validated($request);
        $category = new Category($data);
        $category->slug = ($data['slug'] ?? null) ?: $slugs->unique(Category::class, $data['name_en']);
        $category->sort_order = $data['sort_order'] ?? ((int) Category::query()->where('parent_id', $category->parent_id)->max('sort_order') + 1);
        $category->save();
        $this->storeImage($request, $category);

        $this->audit->log('category.created', $category, [], $category->only(['name_ar', 'name_en', 'slug', 'parent_id']));
        $this->flushDirectoryCaches();

        return redirect()->route('admin.categories.index')->with('success', __('admin.categories.created'));
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.form', [
            'category' => $category,
            'parents' => Category::query()->roots()->whereKeyNot($category->id)->ordered()->get(),
            'icons' => array_keys(Icons::PATHS),
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validated($request, $category);
        if (! empty($data['parent_id']) && $category->children()->exists()) {
            return back()->withInput()->with('error', __('admin.categories.has_children_cannot_nest'));
        }
        $original = $category->getAttributes();
        $category->fill($data);
        if (! empty($data['slug'])) {
            $category->slug = $data['slug'];
        }
        $category->save();
        $this->storeImage($request, $category);
        if ($request->boolean('remove_image') && $category->image_path) {
            $this->media->delete($category->image_path);
            $category->forceFill(['image_path' => null])->save();
        }

        $this->audit->logChanges('category.updated', $category, $original);
        $this->flushDirectoryCaches();

        return redirect()->route('admin.categories.index')->with('success', __('common.updated'));
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->isReferenced()) {
            return back()->with('error', __('admin.categories.in_use'));
        }
        $this->audit->log('category.deleted', $category, $category->only(['name_ar', 'name_en', 'slug']));
        $this->media->delete($category->image_path);
        $category->delete();
        $this->flushDirectoryCaches();

        return redirect()->route('admin.categories.index')->with('success', __('common.deleted'));
    }

    public function move(Category $category, string $direction): RedirectResponse
    {
        DB::transaction(function () use ($category, $direction) {
            $siblings = Category::query()->where('parent_id', $category->parent_id)->ordered()->lockForUpdate()->get()->values();
            $index = $siblings->search(fn ($c) => $c->id === $category->id);
            $swap = $direction === 'up' ? $index - 1 : $index + 1;
            if ($index === false || ! isset($siblings[$swap])) {
                return;
            }
            $ordered = $siblings->all();
            [$ordered[$index], $ordered[$swap]] = [$ordered[$swap], $ordered[$index]];
            foreach ($ordered as $i => $c) {
                if ($c->sort_order !== $i) {
                    $c->forceFill(['sort_order' => $i])->save();
                }
            }
        });
        $this->flushDirectoryCaches();

        return back();
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('parent_id'), Rule::notIn(array_filter([$category?->id]))],
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('categories', 'slug')->ignore($category?->id)],
            'description_ar' => ['nullable', 'string', 'max:2000'],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'icon' => ['nullable', 'string', Rule::in(array_keys(Icons::PATHS))],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'meta_title_ar' => ['nullable', 'string', 'max:160'],
            'meta_title_en' => ['nullable', 'string', 'max:160'],
            'meta_description_ar' => ['nullable', 'string', 'max:300'],
            'meta_description_en' => ['nullable', 'string', 'max:300'],
            'image' => ['nullable', 'image', 'mimetypes:'.implode(',', MediaService::IMAGE_MIMES), 'max:'.(int) setting('uploads.max_image_kb', 5120)],
        ]);
        unset($data['image']);
        $data['is_active'] = $request->boolean('is_active');
        $data['is_featured'] = $request->boolean('is_featured');

        return $data;
    }

    private function storeImage(Request $request, Category $category): void
    {
        if ($request->hasFile('image')) {
            $stored = $this->media->storeImage($request->file('image'), 'categories', 1600, 1000);
            $old = $category->image_path;
            $category->forceFill(['image_path' => $stored['path']])->save();
            $this->media->delete($old);
        }
    }
}
