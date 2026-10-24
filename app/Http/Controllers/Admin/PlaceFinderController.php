<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Business;
use App\Models\Category;
use App\Models\Municipality;
use App\Support\Deadline;
use App\Support\Enrichment\Enricher;
use App\Support\Enrichment\EnrichmentException;
use App\Support\Enrichment\GooglePlaces;
use App\Support\Import\CategoryMatcher;
use App\Support\Places\DuplicateListing;
use App\Support\Places\PlaceFinder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Google Maps finder: search an area for a kind of business, see which places
 * the directory already has, compare those with Google and add the new ones.
 */
class PlaceFinderController extends Controller
{
    private const DEADLINE_SECONDS = 45;

    public function __construct(private readonly PlaceFinder $finder) {}

    public function index(CategoryMatcher $categoryMatcher): View
    {
        $categories = Category::query()->active()->roots()->ordered()
            ->with(['children' => fn ($q) => $q->where('is_active', true)])->get();
        $municipalities = Municipality::query()->where('is_active', true)->ordered()
            ->with(['areas' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('name_en')])->get();

        return view('admin.places.index', [
            'enabled' => GooglePlaces::enabled(),
            'categories' => $categories,
            'municipalities' => $municipalities,
            'radii' => PlaceFinder::RADII,
            'linked' => Business::query()->whereNotNull('google_place_id')->count(),
            'fallbackCategory' => $categoryMatcher->id(CategoryMatcher::FALLBACK),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'keyword' => ['required', 'string', 'min:2', 'max:120'],
            'mode' => ['required', Rule::in(['area', 'point'])],
            'municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'lat' => ['required_if:mode,point', 'nullable', 'numeric', 'between:24.4,26.3'],
            'lng' => ['required_if:mode,point', 'nullable', 'numeric', 'between:50.7,51.8'],
            'radius' => ['required_if:mode,point', 'nullable', Rule::in(PlaceFinder::RADII)],
            'page_token' => ['nullable', 'string', 'max:4096'],
        ]);

        $point = $data['mode'] === 'point';
        $area = ! $point && ! empty($data['area_id']) ? Area::query()->with('municipality')->find($data['area_id']) : null;
        $municipality = ! $point && $area === null && ! empty($data['municipality_id']) ? Municipality::query()->find($data['municipality_id']) : null;
        $query = PlaceFinder::query($data['keyword'], $municipality, $area, $point);
        $rectangle = $point ? GooglePlaces::rectangleAround((float) $data['lat'], (float) $data['lng'], (float) $data['radius']) : null;

        Deadline::start(25);
        try {
            $page = $this->finder->search($query, $rectangle, $data['page_token'] ?? null);
        } catch (EnrichmentException $e) {
            return $this->googleError($e);
        }

        return response()->json(['query' => $query, 'rows' => $page['rows'], 'next' => $page['next']]);
    }

    /** Adds one place, with the values the admin checked in the form. */
    public function add(Request $request): JsonResponse
    {
        $data = $request->validate([
            'place_id' => ['required', 'string', 'regex:'.PlaceFinder::PLACE_ID],
            'name_en' => ['sometimes', 'nullable', 'string', 'max:160'],
            'name_ar' => ['sometimes', 'nullable', 'string', 'max:160'],
            'category_id' => ['sometimes', 'nullable', 'integer'],
            'municipality_id' => ['sometimes', 'nullable', 'integer'],
            'area_id' => ['sometimes', 'nullable', 'integer'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'website' => ['sometimes', 'nullable', 'string', 'max:300'],
            'address_en' => ['sometimes', 'nullable', 'string', 'max:300'],
            'hours' => ['sometimes', 'boolean'],
            'publish' => ['sometimes', 'boolean'],
            'force' => ['sometimes', 'boolean'],
        ]);

        Deadline::start(self::DEADLINE_SECONDS);
        try {
            $business = $this->finder->add($data['place_id'], array_diff_key($data, ['place_id' => true]), $request->user());
        } catch (DuplicateListing $e) {
            return response()->json(['status' => 'duplicate', 'message' => __('places.duplicate', ['name' => $e->business->name_en]), 'business' => PlaceFinder::present($e->business->loadMissing(['category', 'municipality', 'area']))], 409);
        } catch (EnrichmentException $e) {
            return $this->googleError($e);
        }

        return response()->json(['status' => 'added', 'message' => __('places.added', ['name' => $business->name_en]), 'business' => PlaceFinder::present($business->load(['category', 'municipality', 'area']))]);
    }

    /**
     * Adds several places with what Google says and the category chosen in the list.
     * Works until the time budget is spent; the browser sends what is left again.
     */
    public function addMany(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.place_id' => ['required', 'string', 'distinct', 'regex:'.PlaceFinder::PLACE_ID],
            'items.*.category_id' => ['nullable', 'integer'],
            'publish' => ['sometimes', 'boolean'],
        ]);

        Deadline::start(self::DEADLINE_SECONDS);
        $results = [];
        foreach ($data['items'] as $item) {
            // Each place needs two Google calls: stop while there is clearly time for them.
            if (! Deadline::has(12)) {
                break;
            }
            $id = $item['place_id'];
            try {
                $input = ['publish' => (bool) ($data['publish'] ?? false)];
                if (! empty($item['category_id'])) {
                    $input['category_id'] = $item['category_id'];
                }
                $business = $this->finder->add($id, $input, $request->user());
                $results[] = ['place_id' => $id, 'status' => 'added', 'business' => PlaceFinder::present($business->load(['category', 'municipality', 'area']))];
            } catch (DuplicateListing $e) {
                $results[] = ['place_id' => $id, 'status' => 'duplicate', 'message' => __('places.duplicate', ['name' => $e->business->name_en]), 'business' => PlaceFinder::present($e->business->loadMissing(['category', 'municipality', 'area']))];
            } catch (ValidationException $e) {
                $results[] = ['place_id' => $id, 'status' => 'failed', 'message' => collect($e->errors())->flatten()->first()];
            } catch (EnrichmentException $e) {
                $results[] = ['place_id' => $id, 'status' => 'failed', 'message' => self::googleMessage($e)];
                // Google refusing or unreachable will not get better within this request.
                if (! in_array($e->getMessage(), ['google_place_not_found', 'invalid_place_id'], true)) {
                    break;
                }
            }
        }

        return response()->json(['results' => $results]);
    }

    /**
     * Compares a listing with a Google place: everything Google adds or says differently goes to
     * the enrichment review screen, where the admin ticks what to update (the link included).
     */
    public function review(Request $request, Enricher $enricher): JsonResponse
    {
        $data = $request->validate([
            'business_id' => ['required', 'integer'],
            'place_id' => ['required', 'string', 'regex:'.PlaceFinder::PLACE_ID],
        ]);
        $business = Business::query()->findOrFail($data['business_id']);
        if ($business->google_place_id !== null && $business->google_place_id !== $data['place_id']) {
            return response()->json(['status' => 'error', 'message' => __('places.errors.linked_elsewhere')], 422);
        }
        $taken = Business::query()->withTrashed()->where('google_place_id', $data['place_id'])->whereKeyNot($business->id)->value('name_en');
        if ($taken !== null) {
            return response()->json(['status' => 'error', 'message' => __('places.errors.already_listed', ['name' => $taken])], 422);
        }

        Deadline::start(self::DEADLINE_SECONDS);
        try {
            $suggestion = $enricher->run($business, ['google' => true, 'website' => false, 'ai' => false, 'compare' => true], $data['place_id']);
        } catch (EnrichmentException $e) {
            return $this->googleError($e);
        }

        if ($suggestion->status !== 'pending' || $suggestion->fields === []) {
            return response()->json(['status' => 'same', 'message' => __('places.same_as_google')]);
        }

        return response()->json(['status' => 'review', 'url' => route('admin.enrichment.show', $suggestion), 'count' => count($suggestion->fields)]);
    }

    public static function googleMessage(EnrichmentException $e): string
    {
        $code = $e->getMessage();
        if (str_starts_with($code, 'google_error:')) {
            $status = substr($code, 13);

            return __('places.errors.google_'.match ($status) {
                'PERMISSION_DENIED' => 'denied',
                'RESOURCE_EXHAUSTED' => 'quota',
                'INVALID_ARGUMENT' => 'invalid',
                default => 'failed',
            });
        }

        return __(match ($code) {
            'google_not_configured' => 'places.errors.not_configured',
            'google_unreachable' => 'places.errors.google_unreachable',
            'google_out_of_time' => 'places.errors.out_of_time',
            'google_place_not_found', 'invalid_place_id' => 'places.errors.place_gone',
            default => 'places.errors.google_failed',
        });
    }

    private function googleError(EnrichmentException $e): JsonResponse
    {
        return response()->json(['status' => 'error', 'message' => self::googleMessage($e)], 502);
    }
}
