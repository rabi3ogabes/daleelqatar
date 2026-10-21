<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Records security-relevant and administrative actions. Secrets are never logged.
 */
class AuditLogger
{
    private const REDACT = ['password', 'password_confirmation', 'remember_token', 'token', 'api_token', 'current_password', 'secret', 'key'];

    public function log(string $action, ?Model $subject = null, array $old = [], array $new = [], array $metadata = [], ?int $userId = null): AuditLog
    {
        $request = request();

        return AuditLog::query()->create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'auditable_type' => $subject ? $subject->getMorphClass() : null,
            'auditable_id' => $subject?->getKey(),
            'old_values' => $this->clean($old) ?: null,
            'new_values' => $this->clean($new) ?: null,
            'metadata' => $this->clean($metadata) ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
        ]);
    }

    /** Logs only the attributes that actually changed on a model that was just saved. */
    public function logChanges(string $action, Model $model, array $original, array $metadata = []): ?AuditLog
    {
        $changes = Arr::except($model->getChanges(), ['updated_at', 'search_document', 'search_vector']);
        if ($changes === []) {
            return null;
        }
        $old = Arr::only($original, array_keys($changes));

        return $this->log($action, $model, $old, $changes, $metadata);
    }

    private function clean(array $values): array
    {
        foreach ($values as $key => $value) {
            if (in_array(strtolower((string) $key), self::REDACT, true)) {
                $values[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $values[$key] = $this->clean($value);
            } elseif ($value instanceof \BackedEnum) {
                $values[$key] = $value->value;
            } elseif (is_string($value) && mb_strlen($value) > 2000) {
                $values[$key] = mb_substr($value, 0, 2000).'…';
            }
        }

        return $values;
    }
}
