<?php

declare(strict_types=1);

namespace App\Notifications;

/** Generic in-app alert for staff queues (claims, verification, reports, messages). */
class StaffAlert extends DirectoryNotification
{
    public function __construct(
        public readonly string $kind,
        public readonly string $message,
        public readonly ?string $route = null,
        public readonly array $params = [],
    ) {}

    protected function type(): string
    {
        return 'staff.'.$this->kind;
    }

    protected function title(): string
    {
        return __('notifications.staff_'.$this->kind.'_title');
    }

    protected function body(): string
    {
        return $this->message;
    }

    protected function link(): ?array
    {
        return $this->route ? [$this->route, $this->params] : null;
    }

    protected function icon(): string
    {
        return 'inbox';
    }

    protected function mailEnabled(object $notifiable): bool
    {
        return false;
    }
}
