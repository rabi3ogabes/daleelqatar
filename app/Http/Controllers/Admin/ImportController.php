<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Support\Import\ColumnMapper;
use App\Support\Import\ImportProcessor;
use App\Support\Import\Readers;
use App\Support\Storage\SupabaseStorageAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Bulk import of companies from spreadsheets (.xlsx / .csv): upload → review
 * the detected columns → import in resumable chunks → report.
 */
class ImportController extends Controller
{
    public const MAX_MB = 50;

    public function __construct(private readonly ImportProcessor $processor) {}

    public function index(): View
    {
        return view('admin.imports.index', [
            'batches' => ImportBatch::query()->with('user:id,name')->latest('id')->paginate(15),
            'directUpload' => $this->supabase() !== null,
            'maxMb' => self::MAX_MB,
        ]);
    }

    /** Classic upload (local storage, or small files). */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.(self::MAX_MB * 1024), function ($attr, $file, $fail) {
                if (! in_array(strtolower($file->getClientOriginalExtension()), Readers::EXTENSIONS, true)) {
                    $fail(__('imports.errors.type'));
                }
            }],
            'source_name' => ['nullable', 'string', 'max:80'],
        ]);

        $file = $data['file'];
        $disk = config('filesystems.imports_disk', 'local');
        $path = $file->storeAs('imports/'.now()->format('Y/m'), Str::uuid().'.'.strtolower($file->getClientOriginalExtension()), $disk);

        $batch = $this->newBatch($data['source_name'] ?? null, $file->getClientOriginalName(), $disk, (string) $path, (int) $file->getSize());

        try {
            $this->processor->inspect($batch);
        } catch (Throwable $e) {
            return redirect()->route('admin.imports.show', $batch)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.imports.show', $batch);
    }

    /** Direct upload: returns a one-time URL the browser PUTs the file to (Supabase Storage). */
    public function sign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', function ($attr, $name, $fail) {
                if (! in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), Readers::EXTENSIONS, true)) {
                    $fail(__('imports.errors.type'));
                }
            }],
            'size' => ['required', 'integer', 'min:1', 'max:'.(self::MAX_MB * 1024 * 1024)],
            'source_name' => ['nullable', 'string', 'max:80'],
        ]);

        $adapter = $this->supabase();
        abort_if($adapter === null, 404);

        $ext = strtolower(pathinfo($data['name'], PATHINFO_EXTENSION));
        $path = 'imports/'.now()->format('Y/m').'/'.Str::uuid().'.'.$ext;
        $uploadUrl = $adapter->createSignedUploadUrl($path);
        $batch = $this->newBatch($data['source_name'] ?? null, $data['name'], config('filesystems.imports_disk'), $path, (int) $data['size']);

        return response()->json([
            'upload_url' => $uploadUrl,
            'content_type' => $ext === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv',
            'complete_url' => route('admin.imports.uploaded', $batch),
        ]);
    }

    /** Called by the browser once the direct upload finished. */
    public function uploaded(ImportBatch $batch): JsonResponse
    {
        abort_unless($batch->status === 'uploaded', 409);

        try {
            $this->processor->inspect($batch);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage(), 'redirect' => route('admin.imports.show', $batch)], 422);
        }

        return response()->json(['redirect' => route('admin.imports.show', $batch)]);
    }

    public function show(ImportBatch $batch): View
    {
        $rowsQuery = ImportRow::query()->where('import_batch_id', $batch->id);

        return view('admin.imports.show', [
            'batch' => $batch,
            'fields' => ColumnMapper::labels(),
            'categories' => Category::query()->whereNull('parent_id')->with('children')->orderBy('sort_order')->get(),
            'issues' => $batch->isFinished()
                ? (clone $rowsQuery)->whereIn('status', ['failed', 'skipped'])->orderBy('row_number')->limit(50)->get()
                : collect(),
            'issueCount' => $batch->isFinished() ? (clone $rowsQuery)->whereIn('status', ['failed', 'skipped'])->count() : 0,
        ]);
    }

    /** Confirms the mapping and stages every row. */
    public function start(Request $request, ImportBatch $batch): JsonResponse|RedirectResponse
    {
        abort_unless($batch->status === 'ready', 409);

        $columns = count((array) $batch->columns);
        $data = $request->validate([
            'mapping' => ['required', 'array'],
            'mapping.*' => ['nullable', 'integer', 'min:0', 'max:'.max(0, $columns - 1)],
            'mapping.name' => ['required', 'integer'],
            'source_name' => ['required', 'string', 'max:80'],
            'publish' => ['boolean'],
            'update_existing' => ['boolean'],
            'default_category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
        ]);

        $mapping = array_filter($data['mapping'], fn ($v, $k) => $v !== null && $v !== '' && array_key_exists($k, ColumnMapper::FIELDS), ARRAY_FILTER_USE_BOTH);
        $batch->forceFill([
            'source_name' => $data['source_name'],
            'mapping' => array_map('intval', $mapping),
            'options' => [
                'publish' => $request->boolean('publish'),
                'update_existing' => $request->boolean('update_existing'),
                'default_category_id' => $data['default_category_id'] ?? null,
            ],
            'user_id' => $request->user()->id,
        ])->save();

        try {
            $this->processor->stage($batch);
        } catch (Throwable $e) {
            report($e);

            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->with('error', $e->getMessage());
        }

        return $request->expectsJson() ? response()->json($this->progress($batch->refresh())) : redirect()->route('admin.imports.show', $batch);
    }

    /** Imports the next chunk; the page calls this repeatedly until the import completes. */
    public function process(ImportBatch $batch): JsonResponse
    {
        @set_time_limit(60);

        return response()->json($this->progress($this->processor->process($batch, 20.0)));
    }

    public function cancel(ImportBatch $batch): RedirectResponse
    {
        abort_if($batch->isFinished(), 409);
        $this->processor->cancel($batch);

        return redirect()->route('admin.imports.show', $batch)->with('success', __('imports.cancelled'));
    }

    public function destroy(ImportBatch $batch): RedirectResponse
    {
        abort_if(in_array($batch->status, ImportBatch::ACTIVE, true), 409);
        rescue(fn () => Storage::disk($batch->disk)->delete($batch->path), report: false);
        $batch->delete();

        return redirect()->route('admin.imports.index')->with('success', __('imports.deleted'));
    }

    /** CSV of rows that were skipped or failed, with the reason. */
    public function report(ImportBatch $batch): StreamedResponse
    {
        $name = 'import-report-'.Str::slug($batch->source_name).'-'.$batch->id.'.csv';

        return response()->streamDownload(function () use ($batch) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['row', 'status', 'reason', 'company', 'phone', 'email', 'listing'], ',', '"', '');
            ImportRow::query()->where('import_batch_id', $batch->id)->orderBy('row_number')
                ->chunkById(1000, function ($rows) use ($out) {
                    foreach ($rows as $row) {
                        fputcsv($out, [
                            $row->row_number, $row->status, (string) $row->message,
                            $row->payload['name_en'] ?? $row->payload['name_ar'] ?? '',
                            $row->payload['phone'] ?? '', $row->payload['email'] ?? '',
                            $row->business_id ? route('admin.businesses.show', $row->business_id) : '',
                        ], ',', '"', '');
                    }
                });
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function newBatch(?string $source, string $originalName, string $disk, string $path, int $size): ImportBatch
    {
        $batch = new ImportBatch([
            'source_name' => $source ?: Str::limit(pathinfo($originalName, PATHINFO_FILENAME), 80, ''),
            'original_name' => Str::limit($originalName, 255, ''),
            'disk' => $disk,
            'path' => $path,
            'file_size' => $size,
        ]);
        $batch->user_id = auth()->id();
        $batch->options = ['publish' => true, 'update_existing' => true];
        $batch->save();

        return $batch;
    }

    private function supabase(): ?SupabaseStorageAdapter
    {
        $disk = Storage::disk(config('filesystems.imports_disk', 'local'));
        $adapter = method_exists($disk, 'getAdapter') ? $disk->getAdapter() : null;

        return $adapter instanceof SupabaseStorageAdapter ? $adapter : null;
    }

    private function progress(ImportBatch $batch): array
    {
        return [
            'status' => $batch->status,
            'status_label' => __('imports.status.'.$batch->status),
            'total' => $batch->total_rows,
            'processed' => $batch->processed_rows,
            'created' => $batch->created_count,
            'merged' => $batch->merged_count,
            'skipped' => $batch->skipped_count,
            'failed' => $batch->failed_count,
            'percent' => $batch->progress(),
            'done' => $batch->isFinished(),
            'redirect' => route('admin.imports.show', $batch),
        ];
    }
}
