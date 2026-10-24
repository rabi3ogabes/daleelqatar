<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessSuggestion;
use App\Models\ScrapeSource;
use App\Support\Deadline;
use App\Support\Discovery\DirectoryLookup;
use App\Support\Discovery\DiscoveryException;
use App\Support\Discovery\Fetcher;
use App\Support\Enrichment\AiWriter;
use App\Support\Enrichment\DataQuality;
use App\Support\Enrichment\Enricher;
use App\Support\Enrichment\GooglePlaces;
use App\Support\Enrichment\NameReview;
use App\Support\Enrichment\SuggestionApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Smart enrichment: completes listings from Google Maps, the company website
 * (found from the business e-mail domain when needed) and AI suggestions,
 * with every change reviewed by an admin before it is published.
 */
class EnrichmentController extends Controller
{
    public const SCOPES = ['missing_arabic', 'missing_description', 'missing_website', 'missing_location', 'not_enriched', 'all'];

    public const TABS = ['pending', 'names', 'no_website', 'applied', 'rejected', 'empty'];

    public const NAME_PAGE_SIZES = [25, 50, 100];

    private const BUDGET_SECONDS = 18.0;

    /** Vercel stops a request after 60 s; every step ends well before that. */
    private const DEADLINE_SECONDS = 48.0;

    public function __construct(private readonly Enricher $enricher, private readonly AiWriter $ai) {}

    public function index(Request $request, NameReview $names): View
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'pending';
        $nameFilters = [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 80),
            'source' => in_array($request->query('source'), NameReview::SOURCES, true) ? $request->query('source') : null,
            'per_page' => in_array((int) $request->query('per_page'), self::NAME_PAGE_SIZES, true) ? (int) $request->query('per_page') : 50,
        ];

        $items = $tab === 'names'
            ? $names->query($nameFilters['q'], $nameFilters['source'])
                ->with(['business' => fn ($q) => $q->select(['id', 'name_en', 'name_ar', 'slug', 'status', 'category_id', 'municipality_id'])
                    ->with(['category:id,name_ar,name_en', 'municipality:id,name_ar,name_en'])])
                ->orderBy('id')->paginate($nameFilters['per_page'])->withQueryString()
            : ($tab === 'no_website'
            ? $this->scope(Business::query(), 'missing_website')->where('website_status', 'none')->with('category:id,name_ar,name_en')
                ->orderBy('name_en')->paginate(25)->withQueryString()
            : BusinessSuggestion::query()->whereIn('status', $tab === 'applied' ? ['applied', 'partial'] : [$tab])
                ->with(['business:id,name_ar,name_en,slug,owner_id,status', 'reviewer:id,name'])
                ->latest('id')->paginate(25)->withQueryString());

        return view('admin.enrichment.index', [
            'tab' => $tab,
            'nameFilters' => $nameFilters,
            'namesPending' => $names->query()->count(),
            'items' => $items,
            'stats' => DataQuality::stats(true),
            'setup' => ['google' => GooglePlaces::enabled(), 'ai' => $this->ai->available()],
        ]);
    }

    /**
     * Bulk review of suggested Arabic names: the rows ticked on a page, or every matching
     * row (minus the ones unticked). Called repeatedly by the page until finished.
     */
    public function names(Request $request, NameReview $names): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'mode' => ['required', Rule::in(['ids', 'all'])],
            'ids' => ['required_if:mode,ids', 'array', 'max:500'],
            'ids.*' => ['integer'],
            'except' => ['array', 'max:5000'],
            'except.*' => ['integer'],
            'edits' => ['array', 'max:500'],
            'edits.*' => ['nullable', 'string', 'max:160'],
            'cursor' => ['nullable', 'integer', 'min:0'],
            'q' => ['nullable', 'string', 'max:80'],
            'source' => ['nullable', Rule::in(NameReview::SOURCES)],
        ]);
        Deadline::start(self::DEADLINE_SECONDS);

        $result = $names->run(
            $data['action'],
            $data['mode'] === 'ids' ? array_map('intval', $data['ids'] ?? []) : null,
            array_map('intval', $data['except'] ?? []),
            array_filter($data['edits'] ?? [], fn ($v) => is_string($v) && trim($v) !== ''),
            (int) ($data['cursor'] ?? 0),
            $request->user(),
            $data['q'] ?? null,
            $data['source'] ?? null,
        );

        return response()->json($result + ['pending' => $names->query()->count()]);
    }

    /** One step of a bulk run, called repeatedly by the page until nothing is left. */
    public function bulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'scope' => ['required', Rule::in(self::SCOPES)],
            'mode' => ['required', Rule::in(['full', 'names'])],
            'cursor' => ['nullable', 'integer', 'min:0'],
            'google' => ['boolean'], 'website' => ['boolean'], 'ai' => ['boolean'],
        ]);
        if ($data['mode'] === 'names' && ! $this->ai->available()) {
            return response()->json(['message' => __('enrichment.errors.ai_not_configured')], 422);
        }

        Deadline::start(self::DEADLINE_SECONDS);
        $cursor = (int) ($data['cursor'] ?? 0);
        $scope = $data['mode'] === 'names' ? 'missing_arabic' : $data['scope'];
        $started = microtime(true);
        $processed = 0;
        $suggested = 0;
        $errors = 0;

        if ($data['mode'] === 'names') {
            $batch = $this->scope(Business::query(), $scope)->where('id', '>', $cursor)->orderBy('id')->limit(40)->get();
            if ($batch->isNotEmpty()) {
                try {
                    $suggested = $this->enricher->translateNames($batch);
                } catch (\Throwable $e) {
                    report($e);
                    $errors = $batch->count();
                }
                $processed = $batch->count();
                $cursor = (int) $batch->last()->id;
            }
        } else {
            $options = ['google' => $request->boolean('google', true), 'website' => $request->boolean('website', true), 'ai' => $request->boolean('ai', true)];
            // The next business only starts when a full check (Google, website, AI) still fits before the deadline.
            while (microtime(true) - $started < self::BUDGET_SECONDS && ($processed === 0 || Deadline::has(32))) {
                $business = $this->scope(Business::query(), $scope)->where('id', '>', $cursor)->orderBy('id')->first();
                if ($business === null) {
                    break;
                }
                $cursor = (int) $business->id;
                try {
                    $suggestion = $this->enricher->run($business, $options);
                    $suggested += $suggestion->status === 'pending' ? 1 : 0;
                } catch (\Throwable $e) {
                    report($e);
                    $errors++;
                }
                $processed++;
            }
        }

        $remaining = $this->scope(Business::query(), $scope)->where('id', '>', $cursor)->count();

        return response()->json([
            'cursor' => $cursor, 'processed' => $processed, 'suggested' => $suggested, 'errors' => $errors,
            'remaining' => $remaining, 'done' => $remaining === 0,
        ]);
    }

    public function business(Request $request, int $business): RedirectResponse
    {
        Deadline::start(self::DEADLINE_SECONDS);
        $model = Business::query()->findOrFail($business);
        $suggestion = $this->enricher->run($model);

        return $suggestion->status === 'pending'
            ? redirect()->route('admin.enrichment.show', $suggestion)
            : back()->with('info', __('enrichment.nothing_new'));
    }

    /**
     * "Import from QBCD": finds the listing on another directory and adds what it knows to the
     * review queue. A sure match is read at once; otherwise the candidates are returned to choose
     * from (or the administrator pastes the page address).
     */
    public function directory(Request $request, int $business): JsonResponse
    {
        $data = $request->validate([
            'source_id' => ['nullable', 'integer'],
            'url' => ['nullable', 'url:http,https', 'max:2000'],
        ]);
        $model = Business::query()->findOrFail($business);
        $sources = DirectoryLookup::sources();
        $source = isset($data['source_id']) ? $sources->firstWhere('id', (int) $data['source_id']) : $sources->first();
        if (! $source instanceof ScrapeSource) {
            return response()->json(['status' => 'error', 'message' => __('enrichment.lookup.no_sources')], 422);
        }

        @set_time_limit(60);
        Deadline::start(self::DEADLINE_SECONDS);
        try {
            $found = (new DirectoryLookup(new Fetcher(600, 12)))->find($model, $source, $data['url'] ?? null);
        } catch (DiscoveryException $e) {
            $message = in_array($e->getMessage(), ['other_site', 'redirect_other_site'], true)
                ? DiscoveryController::reason($e->getMessage())
                : __('enrichment.lookup.failed');

            return response()->json(['status' => 'error', 'message' => $message], 422);
        }

        $payload = ['source' => $source->name, 'candidates' => $found['candidates']];
        if ($found['status'] !== 'matched') {
            return response()->json($payload + [
                'status' => $found['status'],
                'message' => $found['status'] === 'none' ? __('enrichment.lookup.none', ['source' => $source->name]) : __('enrichment.lookup.candidates'),
            ]);
        }

        $before = BusinessSuggestion::query()->where('business_id', $model->id)->pending()->latest('id')->first();
        $beforeCount = count((array) ($before?->fields ?? []));
        $suggestion = $this->enricher->fromDirectory($model, $source, $found['match']);
        $added = count((array) $suggestion->fields) - ($before?->is($suggestion) ? $beforeCount : 0);
        $match = $found['match'];

        return response()->json($payload + [
            'status' => $added > 0 ? 'matched' : 'nothing_new',
            'message' => $added > 0 ? __('enrichment.lookup.matched') : __('enrichment.lookup.nothing_new', ['source' => $source->name]),
            'added' => $added,
            'review_url' => $suggestion->isPending() ? route('admin.enrichment.show', $suggestion) : null,
            'listing' => [
                'title' => $match['title'],
                'url' => $match['url'],
                'score' => (int) round($match['score'] * 100),
                'image' => $match['record']['image_url'] ?? null,
                'categories' => $match['record']['categories'] ?? [],
            ],
        ]);
    }

    public function show(BusinessSuggestion $suggestion): View
    {
        $suggestion->load(['business.category', 'business.municipality', 'business.area', 'reviewer:id,name']);
        $next = BusinessSuggestion::query()->pending()->whereKeyNot($suggestion->id)->where('id', '<', $suggestion->id)->latest('id')->value('id')
            ?? BusinessSuggestion::query()->pending()->whereKeyNot($suggestion->id)->latest('id')->value('id');

        return view('admin.enrichment.show', ['suggestion' => $suggestion, 'business' => $suggestion->business, 'next' => $next]);
    }

    public function approve(Request $request, BusinessSuggestion $suggestion, SuggestionApplier $applier): RedirectResponse
    {
        if (! $suggestion->isPending()) {
            return $this->alreadyReviewed($request, $suggestion);
        }
        $request->validate([
            'apply' => ['required', 'array', 'min:1'],
            'apply.*' => ['in:1'],
            'value' => ['array'],
            'next' => ['nullable', 'integer'],
        ], ['apply.required' => __('enrichment.errors.nothing_selected')]);

        $approved = [];
        foreach (array_keys((array) $request->input('apply')) as $field) {
            $edited = $request->input('value.'.$field);
            if ($field === 'services' && is_array($edited)) {
                $edited = array_values(array_filter($edited, fn ($row) => is_array($row) && empty($row['remove'])));
            }
            $approved[$field] = in_array($field, ['google_place', 'opening_hours', 'social'], true) ? null : $edited;
        }

        $applier->apply($suggestion, $approved, $request->user());

        return $this->afterReview($request, $suggestion, __('enrichment.applied'));
    }

    public function reject(Request $request, BusinessSuggestion $suggestion, SuggestionApplier $applier): RedirectResponse
    {
        if (! $suggestion->isPending()) {
            return $this->alreadyReviewed($request, $suggestion);
        }
        $applier->reject($suggestion, $request->user());

        return $this->afterReview($request, $suggestion, __('enrichment.rejected'));
    }

    /** The admin picked a different Google Maps place for this listing. */
    public function place(Request $request, BusinessSuggestion $suggestion): RedirectResponse
    {
        if (! $suggestion->isPending()) {
            return $this->alreadyReviewed($request, $suggestion);
        }
        $data = $request->validate(['place_id' => ['required', 'string', 'regex:/^[A-Za-z0-9_\-]{10,255}$/']]);
        $new = $this->enricher->run($suggestion->business, [], $data['place_id']);

        return $new->status === 'pending'
            ? redirect()->route('admin.enrichment.show', $new)->with('success', __('enrichment.place_changed'))
            : redirect()->route('admin.enrichment.index')->with('info', __('enrichment.nothing_new'));
    }

    /**
     * A suggestion that was already approved, rejected or replaced — usually a
     * double click or a resubmitted form. The first action stands; nothing changes.
     */
    private function alreadyReviewed(Request $request, BusinessSuggestion $suggestion): RedirectResponse
    {
        $message = __('enrichment.already_reviewed', ['status' => __('enrichment.status.'.$suggestion->status)]);
        $next = $request->integer('next');
        if ($next > 0 && BusinessSuggestion::query()->pending()->whereKey($next)->exists()) {
            return redirect()->route('admin.enrichment.show', $next)->with('info', $message);
        }

        return redirect()->route('admin.enrichment.show', $suggestion)->with('info', $message);
    }

    private function afterReview(Request $request, BusinessSuggestion $suggestion, string $message): RedirectResponse
    {
        $next = $request->integer('next');
        if ($next > 0 && BusinessSuggestion::query()->pending()->whereKey($next)->exists()) {
            return redirect()->route('admin.enrichment.show', $next)->with('success', $message);
        }

        return redirect()->route('admin.enrichment.index')->with('success', $message);
    }

    /** Listings (not deleted, not demo) that a bulk run or a statistic covers. */
    private function scope(Builder $query, string $scope): Builder
    {
        $query->whereNull('businesses.deleted_at')->where('businesses.is_demo', false);

        return match ($scope) {
            'missing_arabic' => $query->whereRaw("coalesce(name_ar, '') !~ '[\\u0600-\\u06FF]'"),
            'missing_description' => $query->where(fn ($q) => $q->whereNull('description_en')->orWhere('description_en', '')->orWhereNull('description_ar')->orWhere('description_ar', '')),
            'missing_website' => $query->where(fn ($q) => $q->whereNull('website')->orWhere('website', '')),
            'missing_location' => $query->whereNull('latitude'),
            'not_enriched' => $query->whereNull('enriched_at'),
            default => $query,
        };
    }
}
