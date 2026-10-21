<?php

declare(strict_types=1);

namespace App\Support\Import;

use RuntimeException;

/** CSV / TSV reader that copes with BOMs, UTF-16 exports and ; or tab delimiters. */
final class CsvReader implements SpreadsheetReader
{
    private string $delimiter;

    public function __construct(private readonly string $path)
    {
        $head = (string) file_get_contents($path, false, null, 0, 64 * 1024);
        if ($head === '') {
            throw new RuntimeException('The file is empty.');
        }
        $head = self::toUtf8($head);
        $firstLine = strtok($head, "\n") ?: $head;
        $counts = [',' => substr_count($firstLine, ','), ';' => substr_count($firstLine, ';'), "\t" => substr_count($firstLine, "\t"), '|' => substr_count($firstLine, '|')];
        arsort($counts);
        $this->delimiter = (string) array_key_first($counts);
    }

    public function sheets(): array
    {
        return ['CSV'];
    }

    public function rows(?string $sheet = null): iterable
    {
        $raw = (string) file_get_contents($this->path);
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, self::toUtf8($raw));
        unset($raw);
        rewind($stream);

        $n = 0;
        try {
            while (($cells = fgetcsv($stream, 0, $this->delimiter, '"', '')) !== false) {
                $n++;
                if ($cells === [null]) {
                    continue;
                }
                yield $n => array_map(fn ($c) => trim((string) $c), $cells);
            }
        } finally {
            fclose($stream);
        }
    }

    private static function toUtf8(string $data): string
    {
        if (str_starts_with($data, "\xEF\xBB\xBF")) {
            return substr($data, 3);
        }
        if (str_starts_with($data, "\xFF\xFE") || str_starts_with($data, "\xFE\xFF")) {
            return (string) mb_convert_encoding(substr($data, 2), 'UTF-8', str_starts_with($data, "\xFF\xFE") ? 'UTF-16LE' : 'UTF-16BE');
        }
        if (! mb_check_encoding($data, 'UTF-8')) {
            return (string) mb_convert_encoding($data, 'UTF-8', 'Windows-1256,ISO-8859-1');
        }

        return $data;
    }
}
