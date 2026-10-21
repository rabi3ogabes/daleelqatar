<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SocialPlatform;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessSocialLink extends Model
{
    protected $fillable = ['platform', 'url'];

    protected function casts(): array
    {
        return ['platform' => SocialPlatform::class];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
