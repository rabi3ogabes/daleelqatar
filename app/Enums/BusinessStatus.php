<?php

declare(strict_types=1);

namespace App\Enums;

enum BusinessStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function label(): string
    {
        return __('status.business.'.$this->value);
    }

    /** Visual tone used by status badges. */
    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Suspended => 'danger',
            self::Archived => 'muted',
        };
    }

    /** @return list<self> Allowed next states (the moderation workflow). */
    public function transitions(): array
    {
        return match ($this) {
            self::Draft => [self::Pending, self::Approved, self::Archived],
            self::Pending => [self::Approved, self::Rejected, self::Draft, self::Archived],
            self::Approved => [self::Suspended, self::Archived, self::Pending],
            self::Rejected => [self::Pending, self::Draft, self::Archived],
            self::Suspended => [self::Approved, self::Archived],
            self::Archived => [self::Draft],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->transitions(), true);
    }

    /** Owners may edit and resubmit their own listing in these states. */
    public function isEditableByOwner(): bool
    {
        return in_array($this, [self::Draft, self::Rejected, self::Approved, self::Pending], true);
    }

    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
