<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Business;
use App\Models\Category;
use App\Models\Municipality;
use App\Models\QuoteRequest;
use App\Support\NavigationData;
use App\Support\Quotes\QuoteMatcher;
use App\Support\Quotes\QuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** "Get quotes": one short form, sent to a few matching businesses that can reply. */
class QuoteController extends Controller
{
    public function create(Request $request, NavigationData $nav, QuoteMatcher $matcher): View
    {
        abort_unless(QuoteService::enabled(), 404);

        $origin = ($slug = $request->query('business')) && is_string($slug)
            ? Business::query()->published()->where('slug', $slug)->first() : null;
        $category = $origin?->category
            ?? (($slug = $request->query('category')) && is_string($slug) ? Category::query()->active()->where('slug', $slug)->first() : null);

        // Guests fill the form first; signing in brings them back here with their draft restored.
        if (auth()->guest()) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        seo()->title(__('quotes.page_title'))->description(__('quotes.meta_description'))->noindex();

        return view('web.quotes.create', [
            'origin' => $origin && $matcher->canReceive($origin) ? $origin : null,
            'originUnavailable' => $origin && ! $matcher->canReceive($origin),
            'category' => $category,
            'municipalityId' => $origin?->municipality_id,
            'areaId' => $origin?->area_id,
            'categories' => $nav->rootCategories(),
            'municipalities' => $nav->municipalities(),
            'areaOptions' => $nav->areaOptions(),
            'maxRecipients' => QuoteMatcher::maxRecipients(),
            'expiryDays' => QuoteService::expiryDays(),
        ]);
    }

    /** Live "your request will reach N businesses" counter. */
    public function preview(Request $request, QuoteMatcher $matcher): JsonResponse
    {
        abort_unless(QuoteService::enabled(), 404);

        $category = Category::query()->active()->find((int) $request->query('category_id'));
        if (! $category) {
            return response()->json(['count' => null]);
        }
        $area = Area::query()->active()->find((int) $request->query('area_id'));
        $municipality = $area?->municipality ?? Municipality::query()->active()->find((int) $request->query('municipality_id'));
        $origin = ($slug = $request->query('business')) ? Business::query()->published()->where('slug', (string) $slug)->first() : null;

        $matches = $matcher->match($category, $municipality, $area, $origin, $request->user()?->id);
        $nearby = $municipality ? $matches->where('municipality_id', $municipality->id)->count() : $matches->count();

        return response()->json([
            'count' => $matches->count(),
            'nearby' => $nearby,
            'verified' => $matches->where('is_verified', true)->count(),
        ]);
    }

    public function store(Request $request, QuoteService $quotes): RedirectResponse
    {
        abort_unless(QuoteService::enabled(), 404);

        $data = $request->validate([
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'municipality_id' => ['nullable', 'integer', Rule::exists('municipalities', 'id')],
            'area_id' => ['nullable', 'integer', Rule::exists('areas', 'id')],
            'title' => ['required', 'string', 'min:6', 'max:140'],
            'details' => ['required', 'string', 'min:20', 'max:2000'],
            'timing' => ['required', Rule::in(QuoteRequest::TIMINGS)],
            'contact_method' => ['required', Rule::in(QuoteRequest::CONTACT_METHODS)],
            'contact_phone' => ['nullable', 'required_unless:contact_method,email', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'business' => ['nullable', 'string', 'max:200'],
            'consent' => ['accepted'],
            'website' => ['prohibited'], // honeypot
        ], [], [
            'category_id' => __('quotes.form.category'),
            'title' => __('quotes.form.title'),
            'details' => __('quotes.form.details'),
            'contact_phone' => __('quotes.form.phone'),
        ]);

        $origin = ! empty($data['business']) ? Business::query()->published()->where('slug', $data['business'])->first() : null;
        $quote = $quotes->send($request->user(), $data, $origin);

        return redirect()->route('account.quotes.show', $quote->uuid)->with('quote_sent', true)
            ->with('success', trans_choice('quotes.sent', $quote->recipients_count, ['count' => $quote->recipients_count]));
    }
}
