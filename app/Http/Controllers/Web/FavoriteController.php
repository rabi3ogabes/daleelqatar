<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FavoriteController extends Controller
{
    public function toggle(Request $request, Business $business): JsonResponse|RedirectResponse
    {
        abort_unless($business->isPublished(), 404);
        $user = $request->user();

        $favorited = DB::transaction(function () use ($user, $business) {
            $exists = $user->favorites()->whereKey($business->id)->exists();
            if ($exists) {
                $user->favorites()->detach($business->id);
            } else {
                $user->favorites()->attach($business->id);
            }
            $business->forceFill(['favorites_count' => DB::table('favorites')->where('business_id', $business->id)->count()])->saveQuietly();

            return ! $exists;
        });

        $message = $favorited ? __('business.favorite_added') : __('business.favorite_removed');

        return $request->expectsJson()
            ? response()->json(['favorited' => $favorited, 'message' => $message])
            : back()->with('success', $message);
    }
}
