<?php

declare(strict_types=1);

namespace App\Support\Import;

use RuntimeException;
use XMLReader;
use ZipArchive;

/**
 * Streaming reader for .xlsx workbooks with no third-party dependency.
 * Sheets are read with XMLReader straight from the zip archive, so files with
 * tens of thousands of rows never have to be loaded as a DOM.
 */
final class XlsxReader implements SpreadsheetReader
{
    /** @var array<string, string> sheet name => zip entry */
    private array $sheets = [];

    /** @var list<string>|null */
    private ?array $sharedStrings = null;

    public function __construct(private readonly string $path)
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('The file is not a valid .xlsx workbook.');
        }
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        $zip->close();

        if ($workbook === false || $rels === false) {
            throw new RuntimeException('The workbook structure could not be read.');
        }

        $targets = [];
        $relXml = simplexml_load_string($rels);
        foreach ($relXml?->Relationship ?? [] as $rel) {
            $target = ltrim((string) $rel['Target'], '/');
            $targets[(string) $rel['Id']] = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
        }

        $wb = simplexml_load_string($workbook);
        foreach ($wb?->sheets->sheet ?? [] as $sheet) {
            $rid = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            if (isset($targets[$rid])) {
                $this->sheets[(string) $sheet['name']] = $targets[$rid];
            }
        }

        if ($this->sheets === []) {
            throw new RuntimeException('The workbook has no worksheets.');
        }
    }

    public function sheets(): array
    {
        return array_keys($this->sheets);
    }

    public function rows(?string $sheet = null): iterable
    {
        $entry = $sheet !== null ? ($this->sheets[$sheet] ?? null) : reset($this->sheets);
        if (! $entry) {
            throw new RuntimeException("Worksheet \"{$sheet}\" was not found.");
        }
        $strings = $this->sharedStrings();

        $xml = new XMLReader;
        if (! $xml->open('zip://'.$this->path.'#'.$entry, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('The worksheet could not be opened.');
        }

        $rowNumber = 0;
        $row = [];
        $cellType = null;
        $col = 0;
        $inRow = false;

        try {
            while ($xml->read()) {
                if ($xml->nodeType === XMLReader::ELEMENT && $xml->localName === 'row') {
                    $rowNumber = (int) ($xml->getAttribute('r') ?: $rowNumber + 1);
                    $row = [];
                    $col = 0;
                    $inRow = true;
                    if ($xml->isEmptyElement) {
                        $inRow = false;
                    }

                    continue;
                }

                if ($inRow && $xml->nodeType === XMLReader::ELEMENT && $xml->localName === 'c') {
                    $ref = $xml->getAttribute('r');
                    $col = $ref ? self::columnIndex($ref) : $col + 1;
                    $cellType = $xml->getAttribute('t');
                    if ($xml->isEmptyElement) {
                        continue;
                    }
                    $value = $this->readCell($xml, $cellType, $strings);
                    if ($value !== '') {
                        $row[$col] = $value;
                    }

                    continue;
                }

                if ($inRow && $xml->nodeType === XMLReader::END_ELEMENT && $xml->localName === 'row') {
                    $inRow = false;
                    if ($row !== []) {
                        $max = max(array_keys($row));
                        $filled = array_fill(0, $max + 1, '');
                        foreach ($row as $i => $v) {
                            $filled[$i] = $v;
                        }
                        yield $rowNumber => $filled;
                    }
                }
            }
        } finally {
            $xml->close();
        }
    }

    /** @param list<string> $strings */
    private function readCell(XMLReader $xml, ?string $type, array $strings): string
    {
        $value = '';
        $depth = $xml->depth;
        while ($xml->read()) {
            if ($xml->nodeType === XMLReader::END_ELEMENT && $xml->localName === 'c' && $xml->depth === $depth) {
                break;
            }
            if ($xml->nodeType !== XMLReader::ELEMENT) {
                continue;
            }
            if ($xml->localName === 'v') {
                $value = $xml->readString();
            } elseif ($xml->localName === 't' && $type === 'inlineStr') {
                $value .= $xml->readString();
            }
        }

        return match ($type) {
            's' => $strings[(int) $value] ?? '',
            'b' => $value === '1' ? 'TRUE' : 'FALSE',
            default => self::cleanNumber($value),
        };
    }

    /** @return list<string> */
    private function sharedStrings(): array
    {
        if ($this->sharedStrings !== null) {
            return $this->sharedStrings;
        }
        $this->sharedStrings = [];

        $zip = new ZipArchive;
        $zip->open($this->path, ZipArchive::RDONLY);
        $exists = $zip->locateName('xl/sharedStrings.xml') !== false;
        $zip->close();
        if (! $exists) {
            return $this->sharedStrings;
        }

        $xml = new XMLReader;
        $xml->open('zip://'.$this->path.'#xl/sharedStrings.xml', null, LIBXML_NONET | LIBXML_COMPACT);
        $current = null;
        $phonetic = 0;
        while ($xml->read()) {
            if ($xml->nodeType === XMLReader::ELEMENT) {
                if ($xml->localName === 'si') {
                    $current = '';
                } elseif ($xml->localName === 'rPh') {
                    $phonetic++;
                } elseif ($xml->localName === 't' && $current !== null && $phonetic === 0) {
                    $current .= $xml->readString();
                }
            } elseif ($xml->nodeType === XMLReader::END_ELEMENT) {
                if ($xml->localName === 'si') {
                    $this->sharedStrings[] = $current ?? '';
                    $current = null;
                } elseif ($xml->localName === 'rPh') {
                    $phonetic--;
                }
            }
        }
        $xml->close();

        return $this->sharedStrings;
    }

    /** "AB12" → 27 (zero based). */
    public static function columnIndex(string $ref): int
    {
        $letters = strtoupper(preg_replace('/\d+/', '', $ref) ?? '');
        $n = 0;
        foreach (str_split($letters) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return max(0, $n - 1);
    }

    /** Keeps "25.2207589" but turns "1734.0" or "9.9E+3"-style numbers into readable text. */
    private static function cleanNumber(string $value): string
    {
        if ($value !== '' && is_numeric($value) && str_contains(strtolower($value), 'e')) {
            return rtrim(rtrim(sprintf('%.10F', (float) $value), '0'), '.');
        }

        return preg_match('/^-?\d+\.0+$/', $value) ? (string) (int) $value : $value;
    }
}
