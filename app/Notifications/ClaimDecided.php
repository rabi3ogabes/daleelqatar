<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\RequestStatus;
use App\Models\BusinessClaimRequest;

class ClaimDecided extends DirectoryNotification
{
    public function __construct(public readonly BusinessClaimRequest $claim) {}

    private function approved(): bool
    {
        return $this->claim->status === RequestStatus::Approved;
    }

    protected function type(): string
    {
        return 'claim.'.$this->claim->status->value;
    }

    protected function title(): string
    {
        return $this->approved() ? __('notifications.claim_approved_title') : __('notifications.claim_rejected_title');
    }

    protected function body(): string
    {
        $body = __($this->approved() ? 'notifications.claim_approved_body' : 'notifications.claim_rejected_body', ['business' => $this->claim->business->t('name')]);

        return $this->claim->decision_note ? $body.' '.__('notifications.note', ['note' => $this->claim->decision_note]) : $body;
    }

    protected function link(): ?array
    {
        return $this->approved()
            ? ['account.businesses.show', ['business' => $this->claim->business->uuid]]
            : ['account.claims.index', []];
    }
}
