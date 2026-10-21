<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\BusinessClaimRequest;
use App\Services\AuditLogger;
use App\Services\ClaimService;
use App\Services\MediaService;
use App\Support\AdminNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClaimController extends Controller
{
    public function index(Request $request): View
    {
        $status = RequestStatus::tryFrom((string) $request->query('status', 'pending'));
        $claims = BusinessClaimRequest::query()
            ->with(['business:id,name_ar,name_en,slug,owner_id,category_id,logo_path,cover_path', 'business.category:id,name_ar,name_en,icon', 'user:id,name,email'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.claims.index', ['claims' => $claims, 'status' => $status]);
    }

    public function show(BusinessClaimRequest $claim): View
    {
        $claim->load(['business.owner', 'business.category', 'business.municipality', 'business.area', 'user', 'reviewer:id,name']);

        return view('admin.claims.show', ['claim' => $claim]);
    }

    public function decide(Request $request, BusinessClaimRequest $claim, ClaimService $claims): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => ['nullable', 'string', 'max:2000', Rule::requiredIf($request->input('decision') === 'reject')],
        ]);

        $data['decision'] === 'approve'
            ? $claims->approve($claim, $request->user(), $data['note'] ?? null)
            : $claims->reject($claim, $request->user(), (string) $data['note']);
        AdminNavigation::flush();

        return redirect()->route('admin.claims.show', $claim->id)->with('success', __('admin.claims.decided'));
    }

    /** Redirects to a short-lived signed URL; private documents are never publicly linked. */
    public function proof(BusinessClaimRequest $claim, MediaService $media, AuditLogger $audit): RedirectResponse
    {
        abort_unless($claim->proof_path && $claim->proof_disk, 404);
        $audit->log('claim.proof.viewed', $claim);

        return redirect()->away($media->temporaryUrl($claim->proof_disk, $claim->proof_path, 5));
    }
}
