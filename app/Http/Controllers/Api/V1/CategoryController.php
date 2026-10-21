<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\BusinessStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Support\Directory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    /** Category tree (active only) with published-listing counts. */
    public function index(): AnonymousResourceCollection
    {
        CategoryResource::$counts = $this->counts();

        $roots = Category::query()->active()->roots()->ordered()
            ->with(['children' => fn ($q) => $q->active()])->get();

        return CategoryResource::collection($roots);
    }

    public function show(string $slug): CategoryResource
    {
        CategoryResource::$counts = $this->counts();

        $category = Category::query()->active()->where('slug', $slug)
            ->with(['children' => fn ($q) => $q->active()])->firstOrFail();

        return new CategoryResource($category);
    }

    /** Leaf counts per category, with top-level categories rolling up their sub-categories. */
    private function counts(): array
    {
        $leaf = Cache::remember('api.category_leaf_counts', now()->addMinutes(5), fn () => DB::table('businesses')
            ->where('status', BusinessStatus::Approved->value)->whereNotNull('published_at')->whereNull('deleted_at')
            ->selectRaw('category_id, COUNT(*) AS total')->groupBy('category_id')
            ->pluck('total', 'category_id')->map(fn ($v) => (int) $v)->all());

        return array_replace($leaf, Directory::categoryCounts());
    }
}
