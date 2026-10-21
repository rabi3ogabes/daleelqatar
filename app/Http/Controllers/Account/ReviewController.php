<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Enums\ModerationStatus;
use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\ReviewService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewController extends Controller
{
    /** Published reviews received by the signed-in owner's businesses. */
    public function index(Request $request, SettingsService $settings): View
    {
        $user = $request->user();
        $businesses = $user->businesses()->orderBy('name_en')->get(['id', 'uuid', 'name_ar', 'name_en', 'slug']);
        $filter = $businesses->firstWhere('uuid', $request->query('business'));

        $reviews = Review::query()
            ->whereIn('business_id', $filter ? [$filter->id] : $businesses->pluck('id'))
            ->where('status', ModerationStatus::Approved)
            ->with(['user:id,name', 'business:id,uuid,name_ar,name_en,slug,owner_id'])
            ->orderByRaw('owner_reply IS NULL DESC')->latest()
            ->paginate(10)->withQueryString();

        return view('account.reviews.index', [
            'reviews' => $reviews,
            'businesses' => $businesses,
            'filter' => $filter,
            'repliesEnabled' => $settings->bool('reviews.owner_replies'),
        ]);
    }

    public function reply(Request $request, Review $review, ReviewService $reviews): RedirectResponse
    {
        abort_unless($review->business?->isOwnedBy($request->user()), 404);
        $this->authorize('reply', $review);

        $data = $request->validate(['owner_reply' => ['nullable', 'string', 'max:2000']]);
        $reviews->reply($review, $request->user(), $data['owner_reply'] ?? null);

        return back()->with('success', filled($data['owner_reply'] ?? null) ? __('account.reviews.reply_saved') : __('account.reviews.reply_removed'));
    }

    /** Reviews written by the signed-in user. */
    public function mine(Request $request): View
    {
        $reviews = $request->user()->reviews()->with('business:id,name_ar,name_en,slug,status,published_at')
            ->latest()->paginate(10);

        return view('account.reviews.mine', ['reviews' => $reviews]);
    }

    public function destroy(Request $request, Review $review, ReviewService $reviews): RedirectResponse
    {
        abort_unless($review->user_id === $request->user()->id, 404);
        $this->authorize('delete', $review);

        $business = $review->business;
        $review->delete();
        if ($business) {
            $reviews->refreshRating($business);
        }

        return redirect()->route('account.my-reviews')->with('success', __('account.reviews.deleted'));
    }
}
