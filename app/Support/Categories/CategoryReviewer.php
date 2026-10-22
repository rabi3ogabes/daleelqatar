<?php

declare(strict_types=1);

namespace App\Support\Categories;

use App\Models\Business;
use App\Models\Category;
use App\Models\CategoryReview;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\IndexNow;
use App\Support\AdminNavigation;
use App\Support\Directory;
use App\Support\Enrichment\AiWriter;
use App\Support\Import\CategoryMatcher;
use App\Support\Sitemap;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Runs the category check over listings and carries out the admin's decisions.
 *
 * The classifier handles every listing; the ones it is unsure about get a
 * second opinion from the AI assistant (when configured). Nothing changes on a
 * listing until an admin applies a suggestion.
 */
final class CategoryReviewer
{
    public const SCOPES = ['unchecked', 'other', 'unsure', 'all'];

    /** Below this the AI is asked for a second opinion. */
    public const ASK_AI_BELOW = 75;

    /** Small batches keep each AI answer well inside the request's time limit. */
    public const AI_BATCH = 12;

    public function __construct(
        private readonly CategoryClassifier $classifier,
        private readonly AiWriter $ai,
        private readonly AuditLogger $audit,
    ) {}

    /** Listings a scan covers. Decisions an admin already made are never reopened. */
    public function scope(string $scope): Builder
    {
        $query = Business::query()->where('is_demo', false);
        $decided = fn ($q) => $q->select(DB::raw(1))->from('category_reviews')->whereColumn('category_reviews.business_id', 'businesses.id')->whereIn('status', ['applied', 'kept']);

        return match ($scope) {
            'unchecked' => $query->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('category_reviews')->whereColumn('category_reviews.business_id', 'businesses.id')),
            'other' => $query->where('category_id', $this->classifier->category(CategoryMatcher::FALLBACK)['id'] ?? 0)->whereNotExists($decided),
            'unsure' => $query->whereExists(fn ($q) => $q->select(DB::raw(1))->from('category_reviews')->whereColumn('category_reviews.business_id', 'businesses.id')
                ->where('status', 'pending')->where('confidence', '<', self::ASK_AI_BELOW)->where('method', 'rules')),
            default => $query->whereNotExists($decided),
        };
    }

    /**
     * One step of a scan, from the listing after $cursor.
     *
     * @return array{cursor: int, processed: int, suggested: int, ai: int, done: bool}
     */
    public function scan(string $scope, int $cursor, bool $useAi, float $budgetSeconds = 15.0, int $limit = 400): array
    {
        $started = microtime(true);
        $useAi = $useAi && $this->ai->available();
        $processed = 0;
        $suggested = 0;
        $queue = [];
        $verdicts = [];

        $query = $this->scope($scope)->where('id', '>', $cursor)->orderBy('id')
            ->select(['id', 'category_id', 'name_en', 'name_ar', 'tagline_en', 'tagline_ar', 'description_en', 'description_ar', 'keywords', 'services_text']);

        foreach ($query->limit($limit)->cursor() as $business) {
            $cursor = (int) $business->id;
            $verdict = $this->classifier->classify($business);
            $verdicts[$business->id] = [$business, $verdict];
            if ($useAi && ($verdict['category'] === null || $verdict['confidence'] < self::ASK_AI_BELOW)) {
                $queue[$business->id] = $business;
            }
            $processed++;
            if (count($queue) >= self::AI_BATCH || microtime(true) - $started > $budgetSeconds) {
                break;
            }
        }

        $opinions = [];
        if ($queue !== []) {
            try {
                $opinions = $this->ai->categorize(array_values(array_map(fn (Business $b) => [
                    'id' => (int) $b->id,
                    'name' => $b->name_en,
                    'name_ar' => $b->name_ar,
                    'labels' => CategoryClassifier::signals($b)['labels'],
                    'services' => $b->services_text,
                    'description' => trim($b->description_en.' '.$b->description_ar) ?: null,
                    'current' => $this->classifier->slugOf($b->category_id),
                ], $queue)), $this->taxonomy());
            } catch (\Throwable $e) {
                report($e); // the rules' verdict stands
            }
        }

        foreach ($verdicts as $id => [$business, $verdict]) {
            $review = $this->record($business, $verdict, $opinions[$id] ?? null);
            $suggested += $review->status === 'pending' && $review->suggested_category_id ? 1 : 0;
        }
        Cache::forget('categories.review.stats');

        $done = ! $this->scope($scope)->where('id', '>', $cursor)->exists();

        return ['cursor' => $cursor, 'processed' => $processed, 'suggested' => $suggested, 'ai' => count($opinions), 'done' => $done];
    }

    /** Checks one listing right away (from its admin page). */
    public function check(Business $business, bool $useAi = true): CategoryReview
    {
        $verdict = $this->classifier->classify($business);
        $opinion = null;
        if ($useAi && $this->ai->available() && ($verdict['category'] === null || $verdict['confidence'] < self::ASK_AI_BELOW)) {
            try {
                $opinion = $this->ai->categorize([[
                    'id' => (int) $business->id, 'name' => $business->name_en, 'name_ar' => $business->name_ar,
                    'labels' => CategoryClassifier::signals($business)['labels'], 'services' => $business->services_text,
                    'description' => trim($business->description_en.' '.$business->description_ar) ?: null,
                    'current' => $this->classifier->slugOf($business->category_id),
                ]], $this->taxonomy())[$business->id] ?? null;
            } catch (\Throwable $e) {
                report($e);
            }
        }
        Cache::forget('categories.review.stats');

        return $this->record($business, $verdict, $opinion, force: true);
    }

    /**
     * Stores the verdict. A decision an admin already made is left alone unless $force.
     *
     * @param  array{category: ?string, secondary: list<string>, confidence: int, kind: string, evidence: list<array<string, mixed>>}  $verdict
     * @param  array{category: string, secondary: list<string>, confidence: int, reason_en: string, reason_ar: string}|null  $opinion
     */
    public function record(Business $business, array $verdict, ?array $opinion = null, bool $force = false): CategoryReview
    {
        $current = $this->classifier->slugOf($business->category_id);
        $method = 'rules';
        $reasons = array_map(fn ($e) => ['field' => $e['field'], 'text' => $e['text'], 'keyword' => $e['keyword']], $verdict['evidence']);

        // "Other businesses" from the AI means it could not tell either: the rules' verdict stands.
        if ($opinion !== null && $opinion['category'] !== CategoryMatcher::FALLBACK && ($opinion['confidence'] >= 50 || $verdict['category'] === null)) {
            $method = 'ai';
            $agrees = $opinion['category'] === $verdict['category'];
            $confidence = $agrees ? min(99, max($verdict['confidence'], $opinion['confidence']) + 5) : max(1, $opinion['confidence'] - 5);
            $ruleEvidence = $agrees ? $reasons : [];
            $verdict = $this->classifier->verdict(
                [['slug' => $opinion['category'], 'score' => 10.0, 'evidence' => []], ...array_map(fn ($s) => ['slug' => $s, 'score' => 5.0, 'evidence' => []], $opinion['secondary'])],
                $current,
            );
            $verdict['confidence'] = $confidence;
            $verdict['secondary'] = $opinion['secondary'];
            $reasons = [['field' => 'ai', 'text' => $opinion['reason_en'], 'text_ar' => $opinion['reason_ar']], ...array_slice($ruleEvidence, 0, 3)];
        }

        $suggested = $verdict['category'] !== null ? $this->classifier->category($verdict['category']) : null;
        $status = match (true) {
            $suggested === null => $current === null || $current === CategoryMatcher::FALLBACK ? 'pending' : 'correct',
            $verdict['kind'] === 'same' => 'correct',
            default => 'pending',
        };

        $review = CategoryReview::query()->firstOrNew(['business_id' => $business->id]);
        if (! $force && $review->exists && in_array($review->status, ['applied', 'kept'], true)) {
            return $review;
        }

        $review->fill([
            'current_category_id' => $business->category_id,
            'suggested_category_id' => $status === 'pending' ? ($suggested['id'] ?? null) : $business->category_id,
            'secondary_ids' => array_values(array_filter(array_map(fn ($s) => $this->classifier->category($s)['id'] ?? null, $verdict['secondary']))),
            'confidence' => $suggested ? $verdict['confidence'] : 0,
            'method' => $method,
            'kind' => $status === 'pending' && $suggested ? $verdict['kind'] : 'same',
            'status' => $status,
            'reasons' => array_slice($reasons, 0, 5),
        ]);
        $review->reviewed_by = null;
        $review->reviewed_at = null;
        $review->save();

        return $review;
    }

    /** Moves the listing to the suggested category (or to the one the admin picked). */
    public function apply(CategoryReview $review, ?User $user, ?int $categoryId = null, bool $flush = true): bool
    {
        $business = $review->business;
        $target = $categoryId ?? $review->suggested_category_id;
        if ($business === null || $target === null || ! Category::query()->whereKey($target)->where('is_active', true)->exists()) {
            return false;
        }

        $overridden = $categoryId !== null && $categoryId !== $review->suggested_category_id;
        DB::transaction(function () use ($review, $business, $target, $categoryId, $user, $overridden) {
            $old = (int) $business->category_id;
            $oldSecondary = $business->categories()->pluck('categories.id')->map(fn ($id) => (int) $id)->all();
            $secondary = $categoryId === null || $categoryId === $review->suggested_category_id
                ? array_values(array_unique([...(array) $review->secondary_ids, ...$oldSecondary]))
                : $oldSecondary;
            $secondary = array_slice(array_values(array_filter($secondary, fn ($id) => (int) $id !== (int) $target)), 0, 3);

            // Saved quietly so the "category changed by hand" hook does not mark this review as kept.
            $business->forceFill(['category_id' => $target]);
            Business::withoutEvents(fn () => $business->save());
            $business->categories()->sync($secondary);
            IndexNow::queue($business);

            $review->forceFill([
                'status' => 'applied',
                'suggested_category_id' => $target,
                'reviewed_by' => $user?->id,
                'reviewed_at' => now(),
            ])->save();

            $this->audit->log('business.category.changed', $business, ['category_id' => $old], ['category_id' => (int) $target], [
                'method' => $review->method, 'confidence' => $review->confidence, 'overridden' => $overridden,
            ], $user?->id);
        });

        if ($flush) {
            $this->flush();
        }

        return true;
    }

    /** The admin decided the listing stays where it is. */
    public function keep(CategoryReview $review, ?User $user): void
    {
        $review->forceFill(['status' => 'kept', 'reviewed_by' => $user?->id, 'reviewed_at' => now()])->save();
        Cache::forget('categories.review.stats');
    }

    /**
     * @param  Collection<int, CategoryReview>  $reviews
     */
    public function applyMany(Collection $reviews, ?User $user): int
    {
        $applied = 0;
        foreach ($reviews as $review) {
            $applied += $review->isPending() && $this->apply($review, $user, flush: false) ? 1 : 0;
        }
        if ($applied > 0) {
            $this->flush();
        }

        return $applied;
    }

    /** @return array<string, int> */
    public function stats(): array
    {
        return Cache::remember('categories.review.stats', now()->addMinutes(2), function () {
            $row = DB::table('category_reviews')->selectRaw(<<<'SQL'
                count(*) as checked,
                count(*) filter (where status = 'pending' and suggested_category_id is not null) as proposed,
                count(*) filter (where status = 'pending' and suggested_category_id is not null and confidence >= ?) as high,
                count(*) filter (where status = 'pending' and suggested_category_id is null) as unclear,
                count(*) filter (where status = 'correct') as correct,
                count(*) filter (where status = 'applied') as applied,
                count(*) filter (where status = 'kept') as kept,
                count(*) filter (where method = 'ai') as by_ai
                SQL, [CategoryReview::HIGH])->first();

            $listings = Business::query()->where('is_demo', false);

            return array_map('intval', (array) $row) + [
                'total' => (clone $listings)->count(),
                'in_other' => (clone $listings)->where('category_id', $this->classifier->category(CategoryMatcher::FALLBACK)['id'] ?? 0)->count(),
                'unchecked' => $this->scope('unchecked')->count(),
            ];
        });
    }

    /** @return array<string, string> slug => "Section › Category" (active categories) */
    public function taxonomy(): array
    {
        return Cache::remember('categories.taxonomy.en', now()->addMinutes(10), function () {
            $all = Category::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'slug', 'parent_id', 'name_en']);
            $byId = $all->keyBy('id');
            $out = [];
            foreach ($all as $c) {
                $parent = $c->parent_id ? $byId->get($c->parent_id) : null;
                $out[$c->slug] = $parent ? $parent->name_en.' › '.$c->name_en : $c->name_en;
            }

            return $out;
        });
    }

    public function flush(): void
    {
        Directory::flushCounts();
        Sitemap::flush();
        AdminNavigation::flush();
        Cache::forget('categories.review.stats');
    }
}
