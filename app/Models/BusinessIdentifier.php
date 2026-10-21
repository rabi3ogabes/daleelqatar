<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A normalised phone number, e-mail address or website domain that identifies one listing. */
class BusinessIdentifier extends Model
{
    public $timestamps = false;

    protected $fillable = ['business_id', 'type', 'value'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
