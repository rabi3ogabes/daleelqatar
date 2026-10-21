<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BusinessStatus;
use App\Enums\RequestStatus;
use App\Exceptions\WorkflowException;
use App\Models\Business;
use App\Models\BusinessVerificationRequest;
use App\Models\User;
use App\Notifications\StaffAlert;
use App\Notifications\VerificationDecided;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Verification badge workflow. The badge is only ever granted by an authorised
 * staff member after reviewing the submitted documents.
 */
class VerificationService
{
    public function __construct(
        private readonly MediaService $media,
        private readonly AuditLogger $audit,
    ) {}

    public function request(Business $business, User $owner, array $data, UploadedFile $document): BusinessVerificationRequest
    {
        if ($business->status !== BusinessStatus::Approved) {
            throw new WorkflowException(__('verification.only_approved'));
        }
        if ($business->is_verified) {
            throw new WorkflowException(__('verification.already_verified'));
        }
        if ($business->verificationRequests()->where('status', RequestStatus::Pending)->exists()) {
            throw new WorkflowException(__('verification.already_pending'));
        }

        $req = DB::transaction(function () use ($business, $owner, $data, $document) {
            $req = new BusinessVerificationRequest($data);
            $req->business_id = $business->id;
            $req->user_id = $owner->id;
            $req->status = RequestStatus::Pending;
            [$req->document_disk, $req->document_path] = $this->media->storePrivateDocument($document, "verifications/{$business->uuid}");
            $req->save();
            $this->audit->log('verification.requested', $req, [], ['business_id' => $business->id], [], $owner->id);

            return $req;
        });

        Notification::send(User::permission('businesses.verify')->get()->merge(User::role('super-admin')->get())->unique('id'),
            new StaffAlert('verification', __('notifications.verification_submitted', ['business' => $business->t('name')]), 'admin.verifications.show', ['verification' => $req->id]));

        return $req;
    }

    public function approve(BusinessVerificationRequest $req, User $reviewer, ?string $note = null): void
    {
        DB::transaction(function () use ($req, $reviewer, $note) {
            if ($req->status !== RequestStatus::Pending) {
                throw new WorkflowException(__('claims.already_decided'));
            }
            $req->status = RequestStatus::Approved;
            $req->reviewed_by = $reviewer->id;
            $req->reviewed_at = now();
            $req->decision_note = $note;
            $req->save();

            $this->verify($req->business, $reviewer);
            $req->user?->notify(new VerificationDecided($req));
        });
    }

    public function reject(BusinessVerificationRequest $req, User $reviewer, string $note): void
    {
        if ($req->status !== RequestStatus::Pending) {
            throw new WorkflowException(__('claims.already_decided'));
        }
        $req->status = RequestStatus::Rejected;
        $req->reviewed_by = $reviewer->id;
        $req->reviewed_at = now();
        $req->decision_note = $note;
        $req->save();
        $this->audit->log('verification.rejected', $req, [], [], ['note' => $note], $reviewer->id);
        $req->user?->notify(new VerificationDecided($req));
    }

    public function verify(Business $business, User $reviewer): void
    {
        $business->forceFill(['is_verified' => true, 'verified_at' => now(), 'verified_by' => $reviewer->id])->save();
        $this->audit->log('business.verified', $business, ['is_verified' => false], ['is_verified' => true], [], $reviewer->id);
    }

    public function revoke(Business $business, User $reviewer, ?string $note = null): void
    {
        $business->forceFill(['is_verified' => false, 'verified_at' => null, 'verified_by' => null])->save();
        $this->audit->log('business.unverified', $business, ['is_verified' => true], ['is_verified' => false], array_filter(['note' => $note]), $reviewer->id);
    }
}
