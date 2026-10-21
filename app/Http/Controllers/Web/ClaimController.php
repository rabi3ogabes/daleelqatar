<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\ClaimService;
use App\Services\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClaimController extends Controller
{
    public function create(Business $business, ClaimService $claims): View
    {
        abort_unless($claims->canBeClaimed($business), 404);

        seo()->title(__('claims.title', ['business' => $business->t('name')]))->noindex();

        return view('web.business.claim', ['business' => $business->load('category', 'municipality', 'area')]);
    }

    public function store(Request $request, Business $business, ClaimService $claims): RedirectResponse
    {
        $maxKb = (int) setting('uploads.max_document_kb', 8192);
        $data = $request->validate([
            'claimant_name' => ['required', 'string', 'max:120'],
            'position' => ['nullable', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^[0-9+\s\-()]{7,32}$/'],
            'email' => ['required', 'email:rfc', 'max:191'],
            'message' => ['nullable', 'string', 'max:2000'],
            'proof' => ['nullable', 'file', 'max:'.$maxKb, 'mimetypes:'.implode(',', MediaService::DOCUMENT_MIMES)],
            'terms' => ['accepted'],
        ]);

        $claims->request($business, $request->user(), collect($data)->except(['proof', 'terms'])->all(), $request->file('proof'));

        return redirect()->route('account.claims.index')->with('success', __('claims.submitted'));
    }
}
