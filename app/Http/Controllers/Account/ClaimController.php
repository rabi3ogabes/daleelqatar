<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\BusinessClaimRequest;
use App\Services\ClaimService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClaimController extends Controller
{
    public function index(Request $request): View
    {
        $claims = $request->user()->claimRequests()->with('business:id,uuid,name_ar,name_en,slug,status,published_at,owner_id')
            ->latest()->paginate(10);

        return view('account.claims', ['claims' => $claims]);
    }

    public function cancel(Request $request, BusinessClaimRequest $claim, ClaimService $claims): RedirectResponse
    {
        abort_unless($claim->user_id === $request->user()->id, 404);
        $claims->cancel($claim);

        return back()->with('success', __('account.claims.cancelled'));
    }
}
