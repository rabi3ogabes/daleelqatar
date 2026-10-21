<?php

declare(strict_types=1);

namespace App\Support\Import;

use RuntimeException;

final class Readers
{
    public const EXTENSIONS = ['xlsx', 'csv', 'tsv', 'txt'];

    public static function open(string $path, string $originalName): SpreadsheetReader
    {
        return match (strtolower(pathinfo($originalName, PATHINFO_EXTENSION))) {
            'xlsx' => new XlsxReader($path),
            'csv', 'tsv', 'txt' => new CsvReader($path),
            default => throw new RuntimeException('Unsupported file type. Upload an .xlsx or .csv file.'),
        };
    }
}
