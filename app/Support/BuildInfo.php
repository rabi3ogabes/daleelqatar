<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * The deployed release, written to public/build/build-info.json by
 * scripts/build-info.mjs during `npm run build` (shown in the admin footer).
 */
final class BuildInfo
{
    /** @return array{version:string, commit:?string, short:?string, branch:?string, updated_at:?CarbonImmutable} */
    public static function get(): array
    {
        $data = [];
        try {
            $path = public_path('build/build-info.json');
            $data = is_file($path) ? (array) json_decode((string) file_get_contents($path), true) : [];
        } catch (Throwable) {
        }

        $commit = is_string($data['commit'] ?? null) && preg_match('/^[0-9a-f]{7,40}$/', $data['commit']) ? $data['commit'] : null;
        $updated = $data['committed_at'] ?? $data['built_at'] ?? null;

        return [
            'version' => is_string($data['version'] ?? null) ? $data['version'] : 'dev',
            'commit' => $commit,
            'short' => $commit ? substr($commit, 0, 7) : null,
            'branch' => is_string($data['branch'] ?? null) ? $data['branch'] : null,
            'updated_at' => rescue(fn () => $updated ? CarbonImmutable::parse($updated)->setTimezone('Asia/Qatar') : null, null, false),
        ];
    }
}
