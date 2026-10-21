<?php

declare(strict_types=1);

namespace App\Support\Import;

interface SpreadsheetReader
{
    /** @return list<string> worksheet names (a CSV has exactly one) */
    public function sheets(): array;

    /**
     * Rows keyed by their 1-based row number; each row is a list of cell strings.
     *
     * @return iterable<int, list<string>>
     */
    public function rows(?string $sheet = null): iterable;
}
