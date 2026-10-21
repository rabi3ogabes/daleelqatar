<?php

declare(strict_types=1);

namespace App\Support\Import;

use RuntimeException;

/**
 * Finds the worksheet and header row that hold the company list, and proposes
 * a column mapping the administrator can confirm or adjust.
 */
final class SpreadsheetInspector
{
    private const SCAN_ROWS = 30;

    private const SAMPLE_ROWS = 5;

    /**
     * @return array{sheet: string, header_row: int, columns: list<string>, mapping: array<string,int>, samples: list<list<string>>}
     */
    public function inspect(SpreadsheetReader $reader): array
    {
        $best = null;
        foreach ($reader->sheets() as $sheet) {
            $candidate = $this->scanSheet($reader, $sheet);
            if ($candidate === null) {
                continue;
            }
            $score = $candidate['score'] + (preg_match('/director|compan|business|data|listing|شركات|دليل/iu', $sheet) ? 2 : 0);
            if ($best === null || $score > $best['total']) {
                $best = $candidate + ['sheet' => $sheet, 'total' => $score];
            }
        }

        if ($best === null) {
            throw new RuntimeException(__('imports.errors.no_header'));
        }

        return [
            'sheet' => $best['sheet'],
            'header_row' => $best['header_row'],
            'columns' => $best['columns'],
            'mapping' => ColumnMapper::detect($best['columns']),
            'samples' => $best['samples'],
        ];
    }

    /** @return array{score:int, header_row:int, columns:list<string>, samples:list<list<string>>}|null */
    private function scanSheet(SpreadsheetReader $reader, string $sheet): ?array
    {
        $found = null;
        $samples = [];
        $scanned = 0;
        foreach ($reader->rows($sheet) as $number => $cells) {
            if ($found === null) {
                $score = ColumnMapper::score($cells);
                if ($score >= 2) {
                    $found = ['score' => $score, 'header_row' => $number, 'columns' => array_map('strval', $cells)];
                }
                if (++$scanned >= self::SCAN_ROWS && $found === null) {
                    return null;
                }

                continue;
            }
            if (array_filter($cells, fn ($c) => $c !== '') !== []) {
                $samples[] = $cells;
            }
            if (count($samples) >= self::SAMPLE_ROWS) {
                break;
            }
        }

        return $found ? $found + ['samples' => $samples] : null;
    }
}
