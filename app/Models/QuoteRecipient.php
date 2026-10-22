<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuoteRecipientStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A business that received a quote request, and its answer. */
class QuoteRecipient extends Model
{
    protected $table = 'quote_request_recipients';

    protected $guarded = ['id'];

    public const DECLINE_REASONS = ['not_available', 'not_our_service', 'outside_area', 'other'];

    protected function casts(): array
    {
        return [
            'status' => QuoteRecipientStatus::class,
            'viewed_at' => 'datetime',
            'replied_at' => 'datetime',
        ];
    }

    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class)->withTrashed();
    }
}
