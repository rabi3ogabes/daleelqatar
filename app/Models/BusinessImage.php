<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BusinessImage extends Model
{
    use HasTranslations;

    protected $fillable = ['disk', 'path', 'caption_ar', 'caption_en', 'width', 'height', 'size_bytes', 'sort_order'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
