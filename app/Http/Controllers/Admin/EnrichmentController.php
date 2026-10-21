<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessSuggestion;
use App\Support\Enrichment\AiWriter;
use App\Support\Enrichment\DataQuality;
use App\Support\Enrichment\Enricher;
use App\Support\Enrichment\GooglePlaces;
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

    public const TABS = ['pending', 'no_website', 'applied', 'rejected', 'empty'];

    private const BUDGET_SECONDS = 18.0;

    public function __construct(private readonly Enricher $enricher, private readonly AiWriter $ai) {}

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'pending';

        $items = $tab === 'no_website'
            ? $this->scope(Business::query(), 'missing_website')->where('website_status', 'none')->with('category:id,name_ar,name_en')
                ->orderBy('name_en')->paginate(25)->withQueryString()
            : BusinessSuggestion::query()->whereIn('status', $tab === 'applied' ? ['applied', 'partial'] : [$tab])
                ->with(['business:id,name_ar,name_en,slug,owner_id,status', 'reviewer:id,name'])
                ->latest('id')->paginate(25)->withQueryString();

        return view('admin.enrichment.index', [
            'tab' => $tab,
            'items' => $items,
            'stats' => DataQuality::stats(true),
            'setup' => ['google' => GooglePlaces::enabled(), 'ai' => $this->ai->available()],
        ]);
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
            while (microtime(true) - $started < self::BUDGET_SECONDS) {
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
        $model = Business::query()->findOrFail($business);
        $suggestion = $this->enricher->run($model);

        return $suggestion->status === 'pending'
            ? redirect()->route('admin.enrichment.show', $suggestion)
            : back()->with('info', __('enrichment.nothing_new'));
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
