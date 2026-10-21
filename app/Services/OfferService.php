<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OfferStatus;
use App\Models\Business;
use App\Models\Offer;
use App\Models\User;
use App\Notifications\OfferStatusChanged;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class OfferService
{
    /** Workflow flags accepted in $data that are not model attributes. */
    private const CONTROL_KEYS = ['save_as_draft', 'submit', 'is_featured'];

    public function __construct(
        private readonly SlugGenerator $slugs,
        private readonly MediaService $media,
        private readonly SettingsService $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function create(Business $business, User $actor, array $data, ?UploadedFile $image = null): Offer
    {
        return DB::transaction(function () use ($business, $actor, $data, $image) {
            $offer = new Offer(\Illuminate\Support\Arr::except($data, self::CONTROL_KEYS));
            $offer->business_id = $business->id;
            $offer->created_by = $actor->id;
            $offer->slug = $this->slugs->unique(Offer::class, $data['title_en'] ?? $data['title_ar']);
            $offer->status = $this->initialStatus($actor, (bool) ($data['save_as_draft'] ?? false));
            if ($offer->status === OfferStatus::Approved) {
                $offer->approved_by = $actor->id;
                $offer->approved_at = now();
            }
            if ($actor->isStaff() && array_key_exists('is_featured', $data)) {
                $offer->is_featured = (bool) $data['is_featured'];
            }
            $offer->save();

            if ($image) {
                $this->setImage($offer, $image);
            }
            $this->audit->log('offer.created', $offer, [], ['status' => $offer->status->value], [], $actor->id);

            return $offer;
        });
    }

    public function update(Offer $offer, User $actor, array $data, ?UploadedFile $image = null): Offer
    {
        return DB::transaction(function () use ($offer, $actor, $data, $image) {
            $original = $offer->getAttributes();
            $offer->fill(\Illuminate\Support\Arr::except($data, self::CONTROL_KEYS));
            if ($actor->isStaff() && array_key_exists('is_featured', $data)) {
                $offer->is_featured = (bool) $data['is_featured'];
            }
            // Owner edits to promotional content go back through moderation when it is required.
            if (! $actor->isStaff() && $offer->isDirty(['title_ar', 'title_en', 'description_ar', 'description_en', 'discount_label_ar', 'discount_label_en', 'terms_ar', 'terms_en'])
                && $this->settings->bool('offers.require_moderation') && $offer->status !== OfferStatus::Draft) {
                $offer->status = OfferStatus::Pending;
            }
            if (! $actor->isStaff() && ($data['submit'] ?? false) && $offer->status === OfferStatus::Draft) {
                $offer->status = $this->initialStatus($actor, false);
            }
            $offer->save();
            if ($image) {
                $this->setImage($offer, $image);
            }
            $this->audit->logChanges('offer.updated', $offer, $original);

            return $offer;
        });
    }

    public function moderate(Offer $offer, User $moderator, OfferStatus $status, ?string $reason = null): Offer
    {
        $from = $offer->status;
        $offer->status = $status;
        $offer->rejection_reason = $status === OfferStatus::Rejected ? $reason : null;
        if ($status === OfferStatus::Approved) {
            $offer->approved_by = $moderator->id;
            $offer->approved_at = now();
        }
        $offer->save();
        $this->audit->log('offer.'.$status->value, $offer, ['status' => $from->value], ['status' => $status->value], array_filter(['reason' => $reason]), $moderator->id);

        $owner = $offer->business?->owner;
        if ($owner && in_array($status, [OfferStatus::Approved, OfferStatus::Rejected], true)) {
            $owner->notify(new OfferStatusChanged($offer));
        }

        return $offer;
    }

    public function delete(Offer $offer, User $actor): void
    {
        $this->audit->log('offer.deleted', $offer, ['title_en' => $offer->title_en], [], [], $actor->id);
        $offer->delete();
    }

    public function setImage(Offer $offer, UploadedFile $image): void
    {
        $stored = $this->media->storeImage($image, "offers/{$offer->uuid}", 1600, 1200);
        $old = $offer->image_path;
        $offer->forceFill(['image_path' => $stored['path']])->save();
        $this->media->delete($old);
    }

    private function initialStatus(User $actor, bool $draft): OfferStatus
    {
        if ($draft) {
            return OfferStatus::Draft;
        }
        if ($actor->isStaff() || ! $this->settings->bool('offers.require_moderation')) {
            return OfferStatus::Approved;
        }

        return OfferStatus::Pending;
    }
}
