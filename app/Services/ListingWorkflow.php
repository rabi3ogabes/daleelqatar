<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BusinessStatus;
use App\Exceptions\WorkflowException;
use App\Models\Business;
use App\Models\BusinessStatusChange;
use App\Models\User;
use App\Notifications\ListingStatusChanged;
use App\Notifications\ListingSubmitted;
use App\Support\Permissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * The listing moderation state machine:
 * draft → pending → approved | rejected; approved ⇄ suspended; any → archived.
 * Every transition is validated, row-locked, recorded in the listing history and audited.
 */
class ListingWorkflow
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SettingsService $settings,
    ) {}

    /** Owner submits for review (or is auto-approved when that policy is explicitly enabled). */
    public function submit(Business $business, User $actor): Business
    {
        $missing = $this->missingRequirements($business);
        if ($missing !== []) {
            throw new WorkflowException(__('workflow.incomplete', ['fields' => implode('، ', $missing)]));
        }

        if ($this->settings->bool('listings.auto_approve')) {
            $business = $this->transition($business, BusinessStatus::Approved, $actor, __('workflow.auto_approved'), function (Business $b) use ($actor) {
                $b->submitted_at = now();
                $this->markApproved($b, $actor);
            });
        } else {
            $business = $this->transition($business, BusinessStatus::Pending, $actor, null, function (Business $b) {
                $b->submitted_at = now();
                $b->rejection_reason = null;
            });

            if ($this->settings->bool('notifications.notify_admins_new_listing')) {
                $staff = User::role([Permissions::SUPER_ADMIN, Permissions::ADMIN, Permissions::MODERATOR])->get();
                Notification::send($staff, new ListingSubmitted($business));
            }
        }

        return $business;
    }

    public function approve(Business $business, User $actor, ?string $note = null): Business
    {
        $business = $this->transition($business, BusinessStatus::Approved, $actor, $note, function (Business $b) use ($actor) {
            $this->markApproved($b, $actor);
        });
        $this->notifyOwner($business, 'approved', $note);

        return $business;
    }

    public function reject(Business $business, User $actor, string $reason): Business
    {
        $business = $this->transition($business, BusinessStatus::Rejected, $actor, $reason, function (Business $b) use ($reason) {
            $b->rejection_reason = $reason;
        });
        $this->notifyOwner($business, 'rejected', $reason);

        return $business;
    }

    public function suspend(Business $business, User $actor, string $reason): Business
    {
        $business = $this->transition($business, BusinessStatus::Suspended, $actor, $reason, function (Business $b) use ($reason) {
            $b->moderation_notes = trim(($b->moderation_notes ? $b->moderation_notes."\n" : '').now()->toDateString().': '.$reason);
        });
        $this->notifyOwner($business, 'suspended', $reason);

        return $business;
    }

    public function restore(Business $business, User $actor, ?string $note = null): Business
    {
        $business = $this->transition($business, BusinessStatus::Approved, $actor, $note, function (Business $b) use ($actor) {
            $this->markApproved($b, $actor);
        });
        $this->notifyOwner($business, 'approved', $note);

        return $business;
    }

    public function archive(Business $business, User $actor, ?string $note = null): Business
    {
        return $this->transition($business, BusinessStatus::Archived, $actor, $note);
    }

    public function unarchive(Business $business, User $actor): Business
    {
        return $this->transition($business, BusinessStatus::Draft, $actor);
    }

    /** Return an edited approved listing to the review queue (when the re-approval policy is on). */
    public function requireReapproval(Business $business, User $actor): Business
    {
        return $this->transition($business, BusinessStatus::Pending, $actor, __('workflow.reapproval_required'), function (Business $b) {
            $b->submitted_at = now();
        });
    }

    /** @return list<string> Human-readable names of required fields that are missing. */
    public function missingRequirements(Business $business): array
    {
        $missing = [];
        foreach (['name_ar' => 'fields.name_ar', 'name_en' => 'fields.name_en', 'category_id' => 'fields.category', 'municipality_id' => 'fields.municipality', 'description_ar' => 'fields.description_ar', 'description_en' => 'fields.description_en'] as $attr => $label) {
            if (blank($business->{$attr})) {
                $missing[] = __($label);
            }
        }
        if (blank($business->phone) && blank($business->whatsapp) && blank($business->email) && blank($business->website)) {
            $missing[] = __('fields.contact_method');
        }

        return $missing;
    }

    private function markApproved(Business $b, User $actor): void
    {
        $b->approved_at = now();
        $b->approved_by = $actor->id;
        $b->published_at ??= now();
        $b->rejection_reason = null;
    }

    private function transition(Business $business, BusinessStatus $to, User $actor, ?string $note = null, ?callable $mutate = null): Business
    {
        return DB::transaction(function () use ($business, $to, $actor, $note, $mutate) {
            /** @var Business $locked */
            $locked = Business::query()->whereKey($business->getKey())->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (! $from->canTransitionTo($to)) {
                throw new WorkflowException(__('workflow.invalid_transition', ['from' => $from->label(), 'to' => $to->label()]));
            }

            $locked->status = $to;
            if ($mutate) {
                $mutate($locked);
            }
            $locked->save();

            BusinessStatusChange::query()->create([
                'business_id' => $locked->id,
                'user_id' => $actor->id,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'note' => $note,
            ]);

            $this->audit->log('business.status.'.$to->value, $locked, ['status' => $from->value], ['status' => $to->value], array_filter(['note' => $note]), $actor->id);

            $business->setRawAttributes($locked->getAttributes(), true);

            return $business;
        });
    }

    private function notifyOwner(Business $business, string $event, ?string $note): void
    {
        $owner = $business->owner;
        if ($owner && $owner->wantsNotification('listing_updates')) {
            $owner->notify(new ListingStatusChanged($business, $event, $note));
        }
    }
}
