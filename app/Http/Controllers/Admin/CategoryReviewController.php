<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Category;
use App\Models\CategoryReview;
use App\Support\Categories\CategoryReviewer;
use App\Support\Enrichment\AiWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Smart categories: checks every listing against the taxonomy and lets an
 * admin apply the corrections, one by one, in bulk, or a whole group of
 * similar moves at once ("Hotels → Restaurants").
 */
class CategoryReviewController extends Controller
{
    public const TABS = ['proposed', 'unclear', 'applied', 'kept'];

    private const APPLY_BUDGET_SECONDS = 15.0;

    public function __construct(private readonly CategoryReviewer $reviewer) {}

    public function index(Request $request, AiWriter $ai): View
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'proposed';
        $filters = [
            'level' => in_array($request->query('level'), ['high', 'medium', 'low'], true) ? $request->query('level') : null,
            'from' => $request->integer('from') ?: null,
            'to' => $request->integer('to') ?: null,
            'q' => trim((string) $request->query('q')) ?: null,
        ];

        $items = $this->filtered($tab, $filters)
            ->with(['business:id,name_en,name_ar,slug,category_id,keywords,status', 'business.category:id,name_en,name_ar,parent_id', 'current:id,name_en,name_ar,parent_id', 'current.parent:id,name_en,name_ar', 'suggested:id,name_en,name_ar,parent_id', 'suggested.parent:id,name_en,name_ar', 'reviewer:id,name'])
            ->orderByDesc($tab === 'proposed' ? 'confidence' : 'updated_at')->orderBy('id')
            ->paginate(30)->withQueryString();

        return view('admin.category-review.index', [
            'tab' => $tab,
            'filters' => $filters,
            'items' => $items,
            'secondaryCategories' => Category::query()->whereIn('id', $items->pluck('secondary_ids')->flatten()->filter()->unique()->all())->get()->keyBy('id'),
            'stats' => $this->reviewer->stats(),
            'moves' => $this->moves(),
            'categories' => $this->categoryOptions(),
            'aiReady' => $ai->available(),
        ]);
    }

    /** One step of a scan, called repeatedly by the page until nothing is left. */
    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'scope' => ['required', Rule::in(CategoryReviewer::SCOPES)],
            'cursor' => ['nullable', 'integer', 'min:0'],
            'ai' => ['boolean'],
        ]);
        $step = $this->reviewer->scan($data['scope'], (int) ($data['cursor'] ?? 0), $request->boolean('ai'));

        return response()->json($step + ['remaining' => $step['done'] ? 0 : $this->reviewer->scope($data['scope'])->where('id', '>', $step['cursor'])->count()]);
    }

    public function apply(Request $request, CategoryReview $review): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('is_active', true)]]);
        if (! $review->isPending() && empty($data['category_id'])) {
            return $this->done($request, __('category_review.already_done'), 'info');
        }
        $ok = $this->reviewer->apply($review, $request->user(), isset($data['category_id']) ? (int) $data['category_id'] : null);

        return $this->done($request, $ok ? __('category_review.applied_one', ['name' => $review->business?->t('name')]) : __('category_review.pick_category'), $ok ? 'success' : 'error');
    }

    public function keep(Request $request, CategoryReview $review): JsonResponse|RedirectResponse
    {
        $this->reviewer->keep($review, $request->user());

        return $this->done($request, __('category_review.kept_one', ['name' => $review->business?->t('name')]));
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['apply', 'keep'])],
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
        ]);
        $reviews = CategoryReview::query()->pending()->whereIn('id', $data['ids'])->with('business')->get();

        if ($data['action'] === 'apply') {
            $count = $this->reviewer->applyMany($reviews->filter(fn ($r) => $r->suggested_category_id !== null), $request->user());

            return back()->with('success', trans_choice('category_review.applied_many', $count, ['count' => format_number($count)]));
        }
        $reviews->each(fn ($r) => $this->reviewer->keep($r, $request->user()));

        return back()->with('success', trans_choice('category_review.kept_many', $reviews->count(), ['count' => format_number($reviews->count())]));
    }

    /** Applies the high-confidence suggestions (optionally of one move), in steps the page repeats. */
    public function applyConfident(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'integer'],
            'to' => ['nullable', 'integer'],
            'min' => ['nullable', 'integer', 'min:'.CategoryReview::MEDIUM, 'max:99'],
        ]);
        $query = fn () => CategoryReview::query()->proposed()->where('confidence', '>=', $data['min'] ?? CategoryReview::HIGH)
            ->when($data['from'] ?? null, fn ($q, $id) => $q->where('current_category_id', $id))
            ->when($data['to'] ?? null, fn ($q, $id) => $q->where('suggested_category_id', $id));

        $started = microtime(true);
        $applied = 0;
        while (microtime(true) - $started < self::APPLY_BUDGET_SECONDS) {
            $batch = $query()->with('business')->orderByDesc('confidence')->limit(50)->get();
            if ($batch->isEmpty()) {
                break;
            }
            $done = $this->reviewer->applyMany($batch, $request->user());
            $applied += $done;
            if ($done === 0) {
                break;
            }
        }
        $remaining = $query()->count();

        return response()->json(['applied' => $applied, 'remaining' => $remaining, 'done' => $remaining === 0]);
    }

    /** "Check category" on a listing's admin page. */
    public function check(Request $request, int $business): RedirectResponse
    {
        $model = Business::query()->findOrFail($business);
        $review = $this->reviewer->check($model);

        return back()->with($review->isPending() && $review->suggested_category_id ? 'info' : 'success', $review->isPending() && $review->suggested_category_id
            ? __('category_review.check_found', ['category' => $review->suggested?->t('name')])
            : __('category_review.check_ok'));
    }

    private function filtered(string $tab, array $filters): Builder
    {
        return CategoryReview::query()
            ->whereHas('business')
            ->when($tab === 'proposed', fn ($q) => $q->proposed()->level($filters['level']))
            ->when($tab === 'unclear', fn ($q) => $q->pending()->whereNull('suggested_category_id'))
            ->when(in_array($tab, ['applied', 'kept'], true), fn ($q) => $q->where('status', $tab))
            ->when($filters['from'], fn ($q, $id) => $q->where('current_category_id', $id))
            ->when($filters['to'], fn ($q, $id) => $q->where('suggested_category_id', $id))
            ->when($filters['q'], fn ($q, $term) => $q->whereHas('business', fn ($b) => $b->where(fn ($w) => $w
                ->where('name_en', 'ilike', '%'.addcslashes($term, '%_\\').'%')
                ->orWhere('name_ar', 'ilike', '%'.addcslashes($term, '%_\\').'%'))));
    }

    /** The most common proposed moves, e.g. 120 × "Hotels → International Cuisine". */
    private function moves(): array
    {
        $rows = DB::table('category_reviews')
            ->where('status', 'pending')->whereNotNull('suggested_category_id')
            ->selectRaw('current_category_id, suggested_category_id, count(*) as total, count(*) filter (where confidence >= ?) as high, round(avg(confidence)) as avg_confidence', [CategoryReview::HIGH])
            ->groupBy('current_category_id', 'suggested_category_id')
            ->orderByDesc('total')->limit(12)->get();
        $names = Category::query()->whereIn('id', $rows->pluck('current_category_id')->merge($rows->pluck('suggested_category_id'))->filter()->unique())->get()->keyBy('id');

        return $rows->map(fn ($r) => [
            'from' => $r->current_category_id ? $names->get($r->current_category_id) : null,
            'to' => $names->get($r->suggested_category_id),
            'total' => (int) $r->total,
            'high' => (int) $r->high,
            'confidence' => (int) $r->avg_confidence,
        ])->filter(fn ($m) => $m['to'] !== null)->values()->all();
    }

    /** @return list<array{label: string, options: list<array{id: int, name: string}>}> */
    private function categoryOptions(): array
    {
        $all = Category::query()->where('is_active', true)->orderBy('sort_order')->get();

        return $all->whereNull('parent_id')->map(fn (Category $parent) => [
            'label' => $parent->t('name'),
            'options' => collect([['id' => (int) $parent->id, 'name' => $parent->t('name')]])
                ->merge($all->where('parent_id', $parent->id)->map(fn (Category $c) => ['id' => (int) $c->id, 'name' => $c->t('name')]))
                ->values()->all(),
        ])->values()->all();
    }

    private function done(Request $request, string $message, string $tone = 'success'): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'stats' => $this->reviewer->stats()], $tone === 'error' ? 422 : 200);
        }

        return back()->with($tone, $message);
    }
}
