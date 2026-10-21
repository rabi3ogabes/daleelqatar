<?php

declare(strict_types=1);

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return __('status.user.'.$this->value);
    }

    public function tone(): string
    {
        return $this === self::Active ? 'success' : 'danger';
    }
}
