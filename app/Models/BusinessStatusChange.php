<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BusinessStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessStatusChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['business_id', 'user_id', 'from_status', 'to_status', 'note'];

    protected function casts(): array
    {
        return [
            'from_status' => BusinessStatus::class,
            'to_status' => BusinessStatus::class,
            'created_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
