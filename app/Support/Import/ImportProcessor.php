<?php

declare(strict_types=1);

namespace App\Support\Import;

use App\Models\Business;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Services\AuditLogger;
use App\Services\IndexNow;
use App\Support\Directory;
use App\Support\Localization;
use App\Support\Sitemap;
use App\Support\Storage\SupabaseStorageAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Runs an import in three resumable steps, each short enough for a serverless
 * request: inspect (find the header and propose a mapping) → stage (store
 * every row as a normalised record) → process (import records in chunks).
 */
final class ImportProcessor
{
    private const STAGE_CHUNK = 500;

    public function __construct(
        private readonly BusinessImporter $importer,
        private readonly SpreadsheetInspector $inspector,
        private readonly AuditLogger $audit,
    ) {}

    public function inspect(ImportBatch $batch): ImportBatch
    {
        try {
            $info = $this->inspector->inspect($this->reader($batch));
        } catch (Throwable $e) {
            $batch->forceFill(['status' => 'failed', 'error' => $e->getMessage()])->save();
            throw $e;
        }

        $batch->forceFill([
            'status' => 'ready',
            'sheet' => $info['sheet'],
            'header_row' => $info['header_row'],
            'columns' => $info['columns'],
            'mapping' => $info['mapping'],
            'options' => array_merge($batch->options ?? [], ['samples' => $info['samples']]),
            'error' => null,
        ])->save();

        return $batch;
    }

    /** Reads every data row, normalises it and stores it for processing. */
    public function stage(ImportBatch $batch): ImportBatch
    {
        if ($batch->status !== 'ready') {
            throw new RuntimeException(__('imports.errors.not_ready'));
        }
        if (! isset($batch->mapping['name'])) {
            throw new RuntimeException(__('imports.errors.name_required'));
        }

        $batch->forceFill(['status' => 'staging', 'started_at' => now()])->save();
        $batch->rows()->delete();

        try {
            $buffer = [];
            $total = 0;
            $skipped = 0;
            foreach ($this->reader($batch)->rows($batch->sheet) as $number => $cells) {
                if ($number <= (int) $batch->header_row) {
                    continue;
                }
                $record = RecordBuilder::fromRow($cells, $batch->mapping);
                if ($record === null) {
                    if (array_filter($cells, fn ($c) => trim($c) !== '') !== []) {
                        $skipped++;
                    }

                    continue;
                }
                $buffer[] = ['import_batch_id' => $batch->id, 'row_number' => $number, 'payload' => json_encode($record, JSON_UNESCAPED_UNICODE), 'status' => 'pending'];
                $total++;
                if (count($buffer) >= self::STAGE_CHUNK) {
                    ImportRow::query()->insert($buffer);
                    $buffer = [];
                }
            }
            if ($buffer !== []) {
                ImportRow::query()->insert($buffer);
            }
        } catch (Throwable $e) {
            $batch->rows()->delete();
            $batch->forceFill(['status' => 'failed', 'error' => $e->getMessage()])->save();
            throw $e;
        }

        $batch->forceFill([
            'status' => $total > 0 ? 'processing' : 'completed',
            'total_rows' => $total,
            'processed_rows' => 0,
            'created_count' => 0,
            'merged_count' => 0,
            'skipped_count' => $skipped,
            'failed_count' => 0,
            'finished_at' => $total > 0 ? null : now(),
        ])->save();

        $this->cleanupLocal($batch);

        return $batch;
    }

    /**
     * Imports pending rows until the time budget is used. Safe to call
     * repeatedly (and concurrently): a lock keeps one worker per batch.
     */
    public function process(ImportBatch $batch, float $seconds = 20.0): ImportBatch
    {
        if ($batch->status !== 'processing') {
            return $batch;
        }

        $lock = Cache::lock('import-batch:'.$batch->id, (int) ceil($seconds) + 30);
        if (! $lock->get()) {
            return $batch->refresh();
        }

        $deadline = microtime(true) + $seconds;
        $sourceKey = $batch->sourceKey();
        $options = [
            'publish' => (bool) $batch->option('publish', true),
            'update_existing' => (bool) $batch->option('update_existing', true),
            'default_category_id' => $batch->option('default_category_id'),
        ];

        try {
            while (microtime(true) < $deadline) {
                $rows = ImportRow::query()->where('import_batch_id', $batch->id)->where('status', 'pending')->orderBy('id')->limit(50)->get();
                if ($rows->isEmpty()) {
                    break;
                }
                $counts = ['created' => 0, 'merged' => 0, 'skipped' => 0, 'failed' => 0];
                foreach ($rows as $row) {
                    try {
                        $result = IndexNow::paused(fn () => $this->importer->import($row->payload, $sourceKey, $options));
                        $row->forceFill(['status' => $result['status'], 'business_id' => $result['business_id'], 'message' => $result['message']])->save();
                        $counts[$result['status']]++;
                    } catch (Throwable $e) {
                        report($e);
                        $row->forceFill(['status' => 'failed', 'message' => mb_substr($e->getMessage(), 0, 250)])->save();
                        $counts['failed']++;
                    }
                    if (microtime(true) >= $deadline) {
                        break;
                    }
                }
                DB::table('import_batches')->where('id', $batch->id)->update([
                    'processed_rows' => DB::raw('processed_rows + '.array_sum($counts)),
                    'created_count' => DB::raw('created_count + '.$counts['created']),
                    'merged_count' => DB::raw('merged_count + '.$counts['merged']),
                    'skipped_count' => DB::raw('skipped_count + '.$counts['skipped']),
                    'failed_count' => DB::raw('failed_count + '.$counts['failed']),
                    'updated_at' => now(),
                ]);
            }

            $batch->refresh();
            if (! ImportRow::query()->where('import_batch_id', $batch->id)->where('status', 'pending')->exists()) {
                $this->finish($batch);
            }
        } finally {
            $lock->release();
        }

        return $batch->refresh();
    }

    public function cancel(ImportBatch $batch): void
    {
        $batch->forceFill(['status' => 'cancelled', 'finished_at' => now()])->save();
        ImportRow::query()->where('import_batch_id', $batch->id)->where('status', 'pending')->delete();
    }

    private function finish(ImportBatch $batch): void
    {
        $batch->forceFill(['status' => 'completed', 'finished_at' => now()])->save();
        Directory::flushCounts();
        Sitemap::flush();
        Cache::forget('map.points.ar');
        Cache::forget('map.points.en');
        $this->submitToIndexNow($batch);
        $this->audit->log('import.completed', null, [], [
            'file' => $batch->original_name,
            'source' => $batch->source_name,
            'rows' => $batch->total_rows,
            'created' => $batch->created_count,
            'merged' => $batch->merged_count,
            'skipped' => $batch->skipped_count,
            'failed' => $batch->failed_count,
        ], [], $batch->user_id);

        // Keep just the names of imported rows for the report; full records stay only for skipped / failed lines.
        ImportRow::query()->where('import_batch_id', $batch->id)->whereIn('status', ['created', 'merged'])
            ->update(['payload' => DB::raw("jsonb_build_object('name_en', payload->>'name_en', 'name_ar', payload->>'name_ar')")]);
    }

    /** Local path of the uploaded file (downloaded once per serverless instance when stored remotely). */
    public function localPath(ImportBatch $batch): string
    {
        $disk = Storage::disk($batch->disk);
        $adapter = method_exists($disk, 'getAdapter') ? $disk->getAdapter() : null;

        if ($adapter instanceof SupabaseStorageAdapter) {
            $target = sys_get_temp_dir().'/dq-import-'.$batch->uuid.'.'.strtolower(pathinfo($batch->original_name, PATHINFO_EXTENSION));
            if (! is_file($target) || filesize($target) === 0) {
                $adapter->downloadTo($batch->path, $target);
            }

            return $target;
        }

        return $disk->path($batch->path);
    }

    private function reader(ImportBatch $batch): SpreadsheetReader
    {
        return Readers::open($this->localPath($batch), $batch->original_name);
    }

    private function cleanupLocal(ImportBatch $batch): void
    {
        $tmp = sys_get_temp_dir().'/dq-import-'.$batch->uuid.'.'.strtolower(pathinfo($batch->original_name, PATHINFO_EXTENSION));
        if (is_file($tmp)) {
            @unlink($tmp);
        }
    }

    private function submitToIndexNow(ImportBatch $batch): void
    {
        if (! IndexNow::enabled()) {
            return;
        }
        $urls = [];
        Business::query()->published()->where('is_demo', false)
            ->whereIn('id', ImportRow::query()->where('import_batch_id', $batch->id)->whereIn('status', ['created', 'merged'])->whereNotNull('business_id')->select('business_id'))
            ->select(['id', 'slug'])->orderBy('id')->each(function (Business $business) use (&$urls) {
                foreach (Localization::LOCALES as $locale) {
                    $urls[] = route($locale.'.business.show', ['business' => $business->slug]);
                }
            });
        IndexNow::submit($urls);
    }
}
