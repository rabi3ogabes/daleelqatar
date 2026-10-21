<?php

declare(strict_types=1);

namespace App\Enums;

enum SocialPlatform: string
{
    case Instagram = 'instagram';
    case X = 'x';
    case Facebook = 'facebook';
    case TikTok = 'tiktok';
    case Snapchat = 'snapchat';
    case LinkedIn = 'linkedin';
    case YouTube = 'youtube';

    public function label(): string
    {
        return match ($this) {
            self::Instagram => 'Instagram',
            self::X => 'X',
            self::Facebook => 'Facebook',
            self::TikTok => 'TikTok',
            self::Snapchat => 'Snapchat',
            self::LinkedIn => 'LinkedIn',
            self::YouTube => 'YouTube',
        };
    }

    /** Hosts accepted for each platform (prevents arbitrary/malicious links). */
    public function hosts(): array
    {
        return match ($this) {
            self::Instagram => ['instagram.com', 'www.instagram.com'],
            self::X => ['x.com', 'www.x.com', 'twitter.com', 'www.twitter.com'],
            self::Facebook => ['facebook.com', 'www.facebook.com', 'm.facebook.com', 'fb.com'],
            self::TikTok => ['tiktok.com', 'www.tiktok.com'],
            self::Snapchat => ['snapchat.com', 'www.snapchat.com'],
            self::LinkedIn => ['linkedin.com', 'www.linkedin.com'],
            self::YouTube => ['youtube.com', 'www.youtube.com', 'youtu.be'],
        };
    }
}
