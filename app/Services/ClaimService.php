<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RequestStatus;
use App\Exceptions\WorkflowException;
use App\Models\Business;
use App\Models\BusinessClaimRequest;
use App\Models\User;
use App\Notifications\ClaimDecided;
use App\Notifications\StaffAlert;
use App\Support\Permissions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Ownership claims for listings that were added without an owner
 * (e.g. by administrators). Ownership is only granted by staff review.
 */
class ClaimService
{
    public function __construct(
        private readonly MediaService $media,
        private readonly AuditLogger $audit,
    ) {}

    public function canBeClaimed(Business $business): bool
    {
        return $business->owner_id === null && $business->isPublished();
    }

    public function request(Business $business, User $user, array $data, ?UploadedFile $proof = null): BusinessClaimRequest
    {
        if (! $this->canBeClaimed($business)) {
            throw new WorkflowException(__('claims.not_claimable'));
        }
        if ($business->claimRequests()->where('user_id', $user->id)->where('status', RequestStatus::Pending)->exists()) {
            throw new WorkflowException(__('claims.already_pending'));
        }

        $claim = DB::transaction(function () use ($business, $user, $data, $proof) {
            $claim = new BusinessClaimRequest($data);
            $claim->business_id = $business->id;
            $claim->user_id = $user->id;
            $claim->status = RequestStatus::Pending;
            if ($proof) {
                [$claim->proof_disk, $claim->proof_path] = $this->media->storePrivateDocument($proof, "claims/{$business->uuid}");
            }
            $claim->save();
            $this->audit->log('claim.requested', $claim, [], ['business_id' => $business->id], [], $user->id);

            return $claim;
        });

        Notification::send(User::permission('claims.review')->get()->merge(User::role(Permissions::SUPER_ADMIN)->get())->unique('id'),
            new StaffAlert('claim', __('notifications.claim_submitted', ['business' => $business->t('name')]), 'admin.claims.show', ['claim' => $claim->id]));

        return $claim;
    }

    public function approve(BusinessClaimRequest $claim, User $reviewer, ?string $note = null): BusinessClaimRequest
    {
        DB::transaction(function () use ($claim, $reviewer, $note) {
            $claim = BusinessClaimRequest::query()->whereKey($claim->id)->lockForUpdate()->firstOrFail();
            if ($claim->status !== RequestStatus::Pending) {
                throw new WorkflowException(__('claims.already_decided'));
            }
            $business = Business::query()->whereKey($claim->business_id)->lockForUpdate()->firstOrFail();
            if ($business->owner_id !== null) {
                throw new WorkflowException(__('claims.already_owned'));
            }

            $business->owner_id = $claim->user_id;
            $business->save();

            $claim->status = RequestStatus::Approved;
            $claim->reviewed_by = $reviewer->id;
            $claim->reviewed_at = now();
            $claim->decision_note = $note;
            $claim->save();

            // Any competing open claims for the same listing are closed.
            BusinessClaimRequest::query()->where('business_id', $business->id)->where('status', RequestStatus::Pending)
                ->whereKeyNot($claim->id)
                ->update(['status' => RequestStatus::Rejected->value, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'decision_note' => __('claims.closed_other_approved')]);

            $owner = User::query()->findOrFail($claim->user_id);
            if (! $owner->hasRole(Permissions::BUSINESS_OWNER)) {
                $owner->assignRole(Permissions::BUSINESS_OWNER);
            }

            $this->audit->log('claim.approved', $claim, [], ['business_id' => $business->id, 'owner_id' => $owner->id], array_filter(['note' => $note]), $reviewer->id);
            $owner->notify(new ClaimDecided($claim));
        });

        return $claim->refresh();
    }

    public function reject(BusinessClaimRequest $claim, User $reviewer, string $note): BusinessClaimRequest
    {
        if ($claim->status !== RequestStatus::Pending) {
            throw new WorkflowException(__('claims.already_decided'));
        }
        $claim->status = RequestStatus::Rejected;
        $claim->reviewed_by = $reviewer->id;
        $claim->reviewed_at = now();
        $claim->decision_note = $note;
        $claim->save();
        $this->audit->log('claim.rejected', $claim, [], [], ['note' => $note], $reviewer->id);
        $claim->user?->notify(new ClaimDecided($claim));

        return $claim;
    }

    public function cancel(BusinessClaimRequest $claim): void
    {
        if ($claim->status === RequestStatus::Pending) {
            $claim->status = RequestStatus::Cancelled;
            $claim->save();
        }
    }
}
