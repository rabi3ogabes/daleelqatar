<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\MediaService;
use App\Services\VerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerificationController extends Controller
{
    public function create(Request $request, Business $business): View
    {
        abort_unless($business->isOwnedBy($request->user()), 404);
        $this->authorize('requestVerification', $business);

        return view('account.businesses.verification', ['business' => $business]);
    }

    public function store(Request $request, Business $business, VerificationService $verification): RedirectResponse
    {
        abort_unless($business->isOwnedBy($request->user()), 404);
        $this->authorize('requestVerification', $business);

        $data = $request->validate([
            'commercial_registration' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9\-\/ ]+$/'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'document' => ['required', 'file', 'max:'.(int) setting('uploads.max_document_kb', 8192), 'mimetypes:'.implode(',', MediaService::DOCUMENT_MIMES)],
        ]);

        $verification->request($business, $request->user(), collect($data)->except('document')->all(), $request->file('document'));

        return redirect()->route('account.businesses.show', $business->uuid)->with('success', __('account.verification.submitted'));
    }
}
