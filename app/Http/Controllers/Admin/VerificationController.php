<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\BusinessVerificationRequest;
use App\Services\AuditLogger;
use App\Services\MediaService;
use App\Services\VerificationService;
use App\Support\AdminNavigation;
use App\Support\Directory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VerificationController extends Controller
{
    public function index(Request $request): View
    {
        $status = RequestStatus::tryFrom((string) $request->query('status', 'pending'));
        $requests = BusinessVerificationRequest::query()
            ->with(['business:id,name_ar,name_en,slug,category_id,logo_path,cover_path,is_verified', 'business.category:id,name_ar,name_en,icon', 'user:id,name,email'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.verifications.index', ['requests' => $requests, 'status' => $status]);
    }

    public function show(BusinessVerificationRequest $verification): View
    {
        $verification->load(['business.owner', 'business.category', 'business.municipality', 'business.area', 'user', 'reviewer:id,name']);

        return view('admin.verifications.show', ['verification' => $verification]);
    }

    public function decide(Request $request, BusinessVerificationRequest $verification, VerificationService $service): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => ['nullable', 'string', 'max:2000', Rule::requiredIf($request->input('decision') === 'reject')],
        ]);

        $data['decision'] === 'approve'
            ? $service->approve($verification, $request->user(), $data['note'] ?? null)
            : $service->reject($verification, $request->user(), (string) $data['note']);
        AdminNavigation::flush();
        Directory::flushCounts();

        return redirect()->route('admin.verifications.show', $verification->id)->with('success', __('admin.verifications.decided'));
    }

    public function document(BusinessVerificationRequest $verification, MediaService $media, AuditLogger $audit): RedirectResponse
    {
        abort_unless($verification->document_path && $verification->document_disk, 404);
        $audit->log('verification.document.viewed', $verification);

        return redirect()->away($media->temporaryUrl($verification->document_disk, $verification->document_path, 5));
    }
}
