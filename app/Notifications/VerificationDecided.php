<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\RequestStatus;
use App\Models\BusinessVerificationRequest;

class VerificationDecided extends DirectoryNotification
{
    public function __construct(public readonly BusinessVerificationRequest $request) {}

    private function approved(): bool
    {
        return $this->request->status === RequestStatus::Approved;
    }

    protected function type(): string
    {
        return 'verification.'.$this->request->status->value;
    }

    protected function title(): string
    {
        return $this->approved() ? __('notifications.verification_approved_title') : __('notifications.verification_rejected_title');
    }

    protected function body(): string
    {
        $body = __($this->approved() ? 'notifications.verification_approved_body' : 'notifications.verification_rejected_body', ['business' => $this->request->business->t('name')]);

        return $this->request->decision_note ? $body.' '.__('notifications.note', ['note' => $this->request->decision_note]) : $body;
    }

    protected function link(): ?array
    {
        return ['account.businesses.show', ['business' => $this->request->business->uuid]];
    }

    protected function icon(): string
    {
        return 'badge-check';
    }
}
