<?php

declare(strict_types=1);

namespace App\Enums;

enum BusinessEventType: string
{
    case View = 'view';
    case Call = 'call';
    case WhatsApp = 'whatsapp';
    case Website = 'website';
    case Directions = 'directions';
    case Share = 'share';
    case OfferView = 'offer_view';
    case PhoneReveal = 'phone_reveal';
    case EmailReveal = 'email_reveal';

    public function label(): string
    {
        return __('status.event.'.$this->value);
    }

    /** Interaction types that may be recorded from the public profile. */
    public static function trackable(): array
    {
        return [self::Call, self::WhatsApp, self::Website, self::Directions, self::Share];
    }
}
