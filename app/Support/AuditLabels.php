<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/** Human-readable, translated labels for audit log actions and subjects. */
final class AuditLabels
{
    public static function action(string $action): string
    {
        $key = 'admin.audit.actions.'.str_replace('.', '_', $action);

        return Lang::has($key) ? __($key) : Str::headline(str_replace('.', ' ', $action));
    }

    public static function subject(?string $type): string
    {
        if (! $type) {
            return '—';
        }
        $key = 'admin.audit.subjects.'.$type;

        return Lang::has($key) ? __($key) : Str::headline(class_basename($type));
    }
}
