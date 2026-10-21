<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Single source of truth for roles, permissions and the role hierarchy.
 */
final class Permissions
{
    public const SUPER_ADMIN = 'super-admin';

    public const ADMIN = 'admin';

    public const MODERATOR = 'moderator';

    public const BUSINESS_OWNER = 'business-owner';

    public const USER = 'user';

    public const STAFF_ROLES = [self::SUPER_ADMIN, self::ADMIN, self::MODERATOR];

    /** Higher rank may manage lower ranks only. */
    public const RANKS = [
        self::SUPER_ADMIN => 100,
        self::ADMIN => 80,
        self::MODERATOR => 50,
        self::BUSINESS_OWNER => 20,
        self::USER => 10,
    ];

    public const ALL = [
        'admin.access',
        'businesses.view', 'businesses.create', 'businesses.update', 'businesses.delete',
        'businesses.moderate', 'businesses.feature', 'businesses.verify', 'businesses.export',
        'claims.review',
        'categories.manage', 'locations.manage',
        'users.view', 'users.manage', 'users.suspend', 'roles.manage',
        'reviews.moderate', 'reports.handle',
        'offers.manage', 'offers.moderate',
        'content.manage', 'messages.manage',
        'settings.manage', 'analytics.view', 'audit.view',
        'imports.manage',
    ];

    /** @return array<string, list<string>> */
    public static function matrix(): array
    {
        return [
            self::SUPER_ADMIN => self::ALL, // also bypasses every gate
            self::ADMIN => array_values(array_diff(self::ALL, ['roles.manage', 'settings.manage'])),
            self::MODERATOR => [
                'admin.access', 'businesses.view', 'businesses.update', 'businesses.moderate',
                'claims.review', 'reviews.moderate', 'reports.handle', 'offers.moderate',
                'messages.manage', 'analytics.view',
            ],
            self::BUSINESS_OWNER => [],
            self::USER => [],
        ];
    }

    public static function roles(): array
    {
        return array_keys(self::RANKS);
    }
}
