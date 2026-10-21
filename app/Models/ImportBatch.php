<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One uploaded spreadsheet and the progress of its import. */
class ImportBatch extends Model
{
    use HasUuids;

    public const ACTIVE = ['staging', 'processing'];

    protected $fillable = ['source_name', 'original_name', 'disk', 'path', 'file_size'];

    protected function casts(): array
    {
        return [
            'columns' => 'array',
            'mapping' => 'array',
            'options' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return data_get($this->options, $key, $default);
    }

    public function progress(): int
    {
        return $this->total_rows > 0 ? (int) min(100, floor(100 * $this->processed_rows / $this->total_rows)) : ($this->status === 'completed' ? 100 : 0);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'cancelled'], true);
    }

    /** Stable source key used in business_sources, e.g. "import:qataryello". */
    public function sourceKey(): string
    {
        return 'import:'.str($this->source_name)->slug()->limit(60, '');
    }
}
