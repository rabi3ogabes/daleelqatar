<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Support\Import\ImportProcessor;
use App\Support\Import\Readers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Imports a spreadsheet from the command line using the same pipeline as the admin dashboard. */
class ImportDirectoryCommand extends Command
{
    protected $signature = 'daleel:import
        {file : Path to an .xlsx or .csv file}
        {--source= : Source name shown in reports (defaults to the file name)}
        {--pending : Send new listings to moderation instead of publishing them}
        {--no-update : Do not fill empty fields of listings that already exist}';

    protected $description = 'Import companies from a spreadsheet, skipping and merging duplicates.';

    public function handle(ImportProcessor $processor): int
    {
        $file = (string) $this->argument('file');
        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (! in_array($ext, Readers::EXTENSIONS, true)) {
            $this->error('Only .xlsx and .csv files are supported.');

            return self::FAILURE;
        }

        $disk = config('filesystems.imports_disk', 'local');
        $path = 'imports/'.now()->format('Y/m').'/'.Str::uuid().'.'.$ext;
        Storage::disk($disk)->put($path, fopen($file, 'rb'));

        $batch = new ImportBatch([
            'source_name' => $this->option('source') ?: pathinfo($file, PATHINFO_FILENAME),
            'original_name' => basename($file),
            'disk' => $disk,
            'path' => $path,
            'file_size' => (int) filesize($file),
        ]);
        $batch->options = ['publish' => ! $this->option('pending'), 'update_existing' => ! $this->option('no-update')];
        $batch->save();

        $processor->inspect($batch);
        $this->info("Sheet \"{$batch->sheet}\", header on row {$batch->header_row}. Columns: ".implode(', ', array_keys($batch->mapping)));

        $started = microtime(true);
        $processor->stage($batch->refresh());
        $this->info("Staged {$batch->total_rows} rows in ".round(microtime(true) - $started, 1).'s.');

        $bar = $this->output->createProgressBar(max(1, $batch->total_rows));
        while ($batch->status === 'processing') {
            $batch = $processor->process($batch, 15);
            $bar->setProgress($batch->processed_rows);
        }
        $bar->finish();
        $this->newLine(2);

        $this->table(['Rows', 'Created', 'Merged', 'Skipped (duplicates / empty)', 'Failed', 'Seconds'], [[
            $batch->total_rows, $batch->created_count, $batch->merged_count, $batch->skipped_count, $batch->failed_count, round(microtime(true) - $started, 1),
        ]]);

        return $batch->failed_count > 0 ? self::FAILURE : self::SUCCESS;
    }
}
