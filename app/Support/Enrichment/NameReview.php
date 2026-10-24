<?php

declare(strict_types=1);

namespace App\Support\Enrichment;

use App\Models\BusinessSuggestion;
use App\Models\User;
use App\Support\Deadline;
use App\Support\Directory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Reviewing suggested Arabic names many at a time: a selection on one page,
 * or everything that matches the filters (minus the rows the admin unticked),
 * worked through in short steps so no request runs too long.
 */
final class NameReview
{
    public const FIELD = 'name_ar';

    public const SOURCES = ['ai', 'google', 'website'];

    public function __construct(private readonly SuggestionApplier $applier) {}

    /** Pending suggestions that propose an Arabic name, with the page's filters. */
    public function query(?string $search = null, ?string $source = null): Builder
    {
        $query = BusinessSuggestion::query()->pending()
            ->whereRaw('jsonb_exists(fields::jsonb, ?)', [self::FIELD])
            ->whereHas('business', fn (Builder $b) => $b->whereNull('deleted_at'));

        if ($source !== null && in_array($source, self::SOURCES, true)) {
            $query->whereRaw("(fields::jsonb) -> ? ->> 'source' = ?", [self::FIELD, $source]);
        }
        $search = trim((string) $search);
        if ($search !== '') {
            $like = '%'.addcslashes(mb_substr($search, 0, 80), '%_\\').'%';
            $query->where(fn (Builder $w) => $w
                ->whereHas('business', fn (Builder $b) => $b->where('name_en', 'ILIKE', $like)->orWhere('name_ar', 'ILIKE', $like))
                ->orWhereRaw("(fields::jsonb) -> ? ->> 'value' ILIKE ?", [self::FIELD, $like]));
        }

        return $query;
    }

    /**
     * One step of a bulk action.
     *
     * @param  'approve'|'reject'  $action
     * @param  list<int>|null  $ids  the chosen rows, or null for "all matching"
     * @param  list<int>  $except  rows to leave out in "all matching" mode
     * @param  array<int|string, string>  $edits  suggestion id => the admin's edited name
     * @return array{processed: int, done: int, failed: list<array{id: int, name: ?string, error: string}>, cursor: int, remaining: int, finished: bool}
     */
    public function run(string $action, ?array $ids, array $except, array $edits, int $cursor, User $by, ?string $search = null, ?string $source = null, float $seconds = 20.0): array
    {
        $started = microtime(true);
        $base = $this->query($search, $source)
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->when($ids === null && $except !== [], fn ($q) => $q->whereNotIn('id', $except));

        $processed = 0;
        $done = 0;
        $failed = [];
        do {
            $batch = (clone $base)->where('id', '>', $cursor)->orderBy('id')->limit(100)->with('business:id,name_en,name_ar')->get();
            foreach ($batch as $suggestion) {
                $cursor = $suggestion->id;
                $processed++;
                try {
                    $done += $this->one($action, $suggestion, $edits[$suggestion->id] ?? $edits[(string) $suggestion->id] ?? null, $by) ? 1 : 0;
                } catch (ValidationException $e) {
                    $failed[] = ['id' => $suggestion->id, 'name' => $suggestion->business?->name_en, 'error' => collect($e->errors())->flatten()->first() ?? __('enrichment.names.failed')];
                } catch (HttpException) {
                    // Reviewed meanwhile (another admin or tab): nothing to do.
                }
                if (microtime(true) - $started > $seconds || ! Deadline::has(6)) {
                    break 2;
                }
            }
        } while ($batch->count() === 100);

        $remaining = (clone $base)->where('id', '>', $cursor)->count();
        if ($done > 0) {
            Directory::flushCounts();
        }

        return ['processed' => $processed, 'done' => $done, 'failed' => $failed, 'cursor' => $cursor, 'remaining' => $remaining, 'finished' => $remaining === 0];
    }

    /** @return bool whether the suggestion was changed */
    private function one(string $action, BusinessSuggestion $suggestion, ?string $edited, User $by): bool
    {
        return DB::transaction(function () use ($action, $suggestion, $edited, $by) {
            // Re-read under a lock: two admins (or two tabs) never apply the same row twice.
            $fresh = BusinessSuggestion::query()->lockForUpdate()->find($suggestion->id);
            if (! $fresh || ! $fresh->isPending() || ! isset($fresh->fields[self::FIELD])) {
                return false;
            }
            $edited = $edited !== null ? trim($edited) : null;
            $action === 'approve'
                ? $this->applier->applyField($fresh, self::FIELD, $edited !== '' ? $edited : null, $by)
                : $this->applier->rejectField($fresh, self::FIELD, $by);

            return true;
        });
    }
}
