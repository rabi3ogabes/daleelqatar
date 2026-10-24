<?php

declare(strict_types=1);

namespace App\Support\Places;

use App\Models\Business;
use RuntimeException;

/** The place looks like a listing the directory already has. */
final class DuplicateListing extends RuntimeException
{
    public function __construct(public readonly Business $business)
    {
        parent::__construct('duplicate_listing');
    }
}
