<?php

declare(strict_types=1);

namespace App\Support\Categories;

use App\Models\Business;
use App\Models\Category;
use Illuminate\Support\Str;

/**
 * Works out which category a business belongs to from everything known about
 * it: the categories other directories gave it (the most specific label counts
 * most, broad section names barely count), its English and Arabic names, its
 * services and its description.
 *
 * Each signal adds weight to the categories its words point to; the result is
 * a ranking with a confidence score and the evidence behind it, so an admin
 * can see why a change is proposed.
 */
final class CategoryClassifier
{
    /** Weight of each kind of evidence. */
    private const WEIGHTS = ['label' => 3.0, 'name' => 3.4, 'name_ar' => 3.4, 'tagline' => 1.4, 'services' => 1.2, 'description' => 0.6];

    /** The most one field may add to one category (long texts mention many things). */
    private const CAPS = ['services' => 3.6, 'description' => 1.8, 'tagline' => 2.8];

    /** Below this total there is not enough evidence to propose anything. */
    public const MIN_SCORE = 1.2;

    /** @var array<string, array{regex: string, specificity: array<string, float>}>|null */
    private static ?array $compiled = null;

    /** @var array<string, array{id: int, slug: string, parent_id: ?int, name_en: string, name_ar: string}>|null slug => category */
    private ?array $categories = null;

    /**
     * @return list<array{slug: string, score: float, evidence: list<array{field: string, text: string, keyword: string, points: float}>}> best first
     */
    public function rank(array $signals): array
    {
        $scores = [];
        $evidence = [];
        $add = function (string $slug, float $points, string $field, string $text, string $keyword) use (&$scores, &$evidence) {
            $scores[$slug] = ($scores[$slug] ?? 0) + $points;
            $evidence[$slug][] = ['field' => $field, 'text' => Str::limit($text, 80), 'keyword' => $keyword, 'points' => round($points, 2)];
        };

        // Source labels: broad section names barely count, job-ad phrases and labels that only repeat the name are skipped.
        $name = self::normalize(trim(($signals['name'] ?? '').' '.($signals['name_ar'] ?? '')));
        $labels = array_values(array_filter(array_map(fn ($l) => trim((string) $l), $signals['labels'] ?? []), function ($l) use ($name) {
            $n = self::normalize($l);

            return $l !== '' && mb_strlen($l) <= 120
                && ! preg_match('/\b(jobs?|vacanc\w*|hiring|careers?|wll|w l l|llc|spc|qpsc|qsc)\b/', $n) // job ads and other company names
                && ! preg_match('/(توظيف|وظائف|وظيفة|موظفين)/u', $l)
                && ($n === '' || $name === '' || (! str_contains($name, $n) && ! str_contains($n, $name)));
        }));
        foreach (array_slice($labels, 0, 12) as $i => $label) {
            $generic = in_array(self::normalize($label), Lexicon::GENERIC_LABELS, true);
            $position = $i >= 4 ? 0.7 : 1.0;
            $matches = $this->matches($label);
            // A label naming two fields ("Schools & Universities") counts half for each.
            $share = 1 / max(1, count($matches));
            foreach ($matches as $slug => [$keyword, $specificity]) {
                // A label is already a category name, so long phrases matter less than in free text.
                $specificity = $specificity >= 1.0 ? min(1.5, 1.0 + ($specificity - 1.0) * 0.4) : $specificity;
                $add($slug, self::WEIGHTS['label'] * $specificity * $position * $share * ($generic ? 0.3 : 1.0), 'label', $label, $keyword);
            }
        }

        foreach (['name' => $signals['name'] ?? null, 'name_ar' => $signals['name_ar'] ?? null] as $field => $text) {
            if (! $text) {
                continue;
            }
            foreach ($this->matches((string) $text) as $slug => [$keyword, $specificity]) {
                $weak = in_array($keyword, Lexicon::WEAK_IN_NAME, true);
                $add($slug, self::WEIGHTS[$field] * $specificity * ($weak ? 0.3 : 1.0), $field, (string) $text, $keyword);
            }
        }

        foreach (['tagline', 'services', 'description'] as $field) {
            $text = trim((string) ($signals[$field] ?? ''));
            if ($text === '') {
                continue;
            }
            $gained = [];
            foreach ($this->matches(Str::limit($text, 3000, ''), all: true) as $slug => $hits) {
                foreach ($hits as [$keyword, $specificity]) {
                    $points = min(self::WEIGHTS[$field] * $specificity, self::CAPS[$field] - ($gained[$slug] ?? 0));
                    if ($points <= 0.01) {
                        break;
                    }
                    $gained[$slug] = ($gained[$slug] ?? 0) + $points;
                    $add($slug, $points, $field, $this->snippet($text, $keyword), $keyword);
                }
            }
        }

        arsort($scores);
        $out = [];
        foreach ($scores as $slug => $score) {
            usort($evidence[$slug], fn ($a, $b) => $b['points'] <=> $a['points']);
            $out[] = ['slug' => $slug, 'score' => round($score, 2), 'evidence' => array_slice($evidence[$slug], 0, 4)];
        }

        return $out;
    }

    /**
     * The verdict for one business.
     *
     * @return array{category: ?string, secondary: list<string>, confidence: int, kind: string, evidence: list<array<string, mixed>>, scores: array<string, float>}
     */
    public function classify(Business $business): array
    {
        $ranking = $this->rank(self::signals($business));
        $current = $this->slugOf($business->category_id);

        return $this->verdict($ranking, $current);
    }

    /**
     * @param  list<array{slug: string, score: float, evidence: list<array<string, mixed>>}>  $ranking
     * @return array{category: ?string, secondary: list<string>, confidence: int, kind: string, evidence: list<array<string, mixed>>, scores: array<string, float>}
     */
    public function verdict(array $ranking, ?string $current): array
    {
        $ranking = array_values(array_filter($ranking, fn ($r) => $this->category($r['slug']) !== null));
        $scores = array_column($ranking, 'score', 'slug');
        $top = $ranking[0] ?? null;
        if ($top === null || $top['score'] < self::MIN_SCORE) {
            return ['category' => null, 'secondary' => [], 'confidence' => 0, 'kind' => $current && $current !== 'other-businesses' ? 'same' : 'fix', 'evidence' => [], 'scores' => $scores];
        }

        $second = $ranking[1]['score'] ?? 0.0;
        $strength = min(1.0, $top['score'] / 4.5);
        $lead = ($top['score'] - $second) / $top['score'];
        $confidence = 100 * (0.5 * $strength + 0.5 * $lead);
        // Companies active in many fields (conglomerates) are less certain.
        $contenders = count(array_filter($ranking, fn ($r) => $r['slug'] !== $top['slug'] && $r['score'] >= 0.6 * $top['score']));
        $confidence -= min(24, 8 * $contenders);
        if ($current !== null && $current !== $top['slug'] && ($scores[$current] ?? 0) >= 0.5 * $top['score']) {
            $confidence -= 15; // the current category is plausible too
        }
        $confidence = (int) round($confidence);

        $slug = $top['slug'];
        $kind = 'fix';
        if ($current !== null) {
            $currentScore = $scores[$current] ?? 0.0;
            if ($slug === $current || $this->parentSlug($current) === $slug) {
                // Already right, or the current category is a more specific child of the winner.
                $kind = 'same';
                $slug = $current;
            } elseif ($currentScore >= 0.8 * $top['score']) {
                // The current category is about as good: leave it alone.
                $kind = 'same';
                $slug = $current;
            } elseif ($this->parentSlug($slug) === $current) {
                $kind = 'refine';
            }
        }

        $secondary = [];
        foreach (array_slice($ranking, 1) as $row) {
            if (count($secondary) >= 2 || $row['score'] < max(2.0, 0.45 * $top['score'])) {
                break;
            }
            if ($row['slug'] !== $slug && $row['slug'] !== $this->parentSlug($slug) && $row['slug'] !== 'other-businesses') {
                $secondary[] = $row['slug'];
            }
        }

        $evidence = collect($ranking)->firstWhere('slug', $slug)['evidence'] ?? $top['evidence'];

        return ['category' => $slug, 'secondary' => $secondary, 'confidence' => max(1, min(99, $confidence)), 'kind' => $kind, 'evidence' => $evidence, 'scores' => $scores];
    }

    /** @return array{labels: list<string>, name: ?string, name_ar: ?string, tagline: ?string, services: ?string, description: ?string} */
    public static function signals(Business $business): array
    {
        $labels = preg_split('/\s*[,;|·\n]\s*/u', (string) $business->keywords) ?: [];

        return [
            'labels' => array_values(array_unique(array_filter($labels))),
            'name' => $business->name_en,
            'name_ar' => $business->name_ar,
            'tagline' => trim($business->tagline_en.' '.$business->tagline_ar) ?: null,
            'services' => $business->services_text,
            'description' => trim($business->description_en.' '.$business->description_ar) ?: null,
        ];
    }

    /** @return array{id: int, slug: string, parent_id: ?int, name_en: string, name_ar: string}|null */
    public function category(?string $slug): ?array
    {
        $this->categories ??= Category::query()->where('is_active', true)->get(['id', 'slug', 'parent_id', 'name_en', 'name_ar'])
            ->mapWithKeys(fn (Category $c) => [$c->slug => ['id' => (int) $c->id, 'slug' => $c->slug, 'parent_id' => $c->parent_id ? (int) $c->parent_id : null, 'name_en' => $c->name_en, 'name_ar' => $c->name_ar]])
            ->all();

        return $slug !== null ? ($this->categories[$slug] ?? null) : null;
    }

    public function slugOf(?int $categoryId): ?string
    {
        $this->category(null);
        foreach ($this->categories as $slug => $c) {
            if ($c['id'] === $categoryId) {
                return $slug;
            }
        }

        return null;
    }

    public function parentSlug(?string $slug): ?string
    {
        $parentId = $this->category($slug)['parent_id'] ?? null;

        return $parentId ? $this->slugOf($parentId) : null;
    }

    public function forget(): void
    {
        $this->categories = null;
    }

    /**
     * Categories whose words appear in the text: slug => [best keyword, specificity],
     * or with $all every hit: slug => list of [keyword, specificity].
     *
     * @return array<string, mixed>
     */
    public function matches(string $text, bool $all = false): array
    {
        $compiled = self::compiled();
        $latin = ' '.self::normalize($text).' ';
        $arabic = self::normalizeArabic($text);
        $hasArabic = preg_match('/\p{Arabic}/u', $text) === 1;
        $out = [];

        foreach ($compiled as $slug => $c) {
            $hits = [];
            if ($c['regex'] !== '' && preg_match_all($c['regex'], $latin, $m)) {
                foreach ($m[1] as $hit) {
                    $keyword = $c['lookup'][$hit] ?? $c['lookup'][preg_replace('/(es|s)$/u', '', $hit)] ?? $c['lookup'][preg_replace('/s$/u', '', $hit)] ?? $hit;
                    $hits[$keyword] = $c['specificity'][$keyword] ?? 1.0;
                }
            }
            if ($hasArabic) {
                foreach ($c['arabic'] as $keyword => $normalized) {
                    if (str_contains($arabic, $normalized)) {
                        $hits[$keyword] = $c['specificity'][$keyword];
                    }
                }
            }
            if ($hits === []) {
                continue;
            }
            arsort($hits);
            $out[$slug] = $all
                ? array_map(fn ($k, $s) => [$k, $s], array_keys($hits), array_values($hits))
                : [array_key_first($hits), reset($hits)];
        }

        return $out;
    }

    public static function normalize(string $text): string
    {
        $text = Str::lower(Str::ascii($text));
        $text = preg_replace('/\band\b/u', '&', $text) ?? $text;
        $text = preg_replace('/[^a-z0-9&\/]+/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/', ' ', str_replace('&', ' & ', $text)) ?? $text);
    }

    public static function normalizeArabic(string $text): string
    {
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text) ?? $text;

        return strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ة' => 'ه']);
    }

    /** @return array<string, array{regex: string, lookup: array<string, string>, specificity: array<string, float>, arabic: array<string, string>}> */
    private static function compiled(): array
    {
        if (self::$compiled !== null) {
            return self::$compiled;
        }

        $out = [];
        foreach (array_unique([...array_keys(Lexicon::EN), ...array_keys(Lexicon::AR)]) as $slug) {
            $lookup = [];
            $specificity = [];
            foreach (Lexicon::EN[$slug] ?? [] as $keyword) {
                $normalized = self::normalize($keyword);
                if ($normalized === '') {
                    continue;
                }
                $words = substr_count($normalized, ' ') + 1 - substr_count($normalized, ' & ');
                $value = min(2.6, 1.0 + 0.6 * ($words - 1));
                if (in_array($normalized, Lexicon::BROAD, true)) {
                    $value *= 0.5;
                }
                $lookup[$normalized] = $keyword;
                $specificity[$keyword] = $value;
            }
            $arabic = [];
            foreach (Lexicon::AR[$slug] ?? [] as $keyword) {
                $arabic[$keyword] = self::normalizeArabic($keyword);
                $specificity[$keyword] = in_array($keyword, Lexicon::WEAK_IN_NAME, true) ? 1.0 : (str_contains($keyword, ' ') ? 1.8 : 1.3);
            }
            $keys = array_keys($lookup);
            usort($keys, fn ($a, $b) => strlen($b) <=> strlen($a));
            $regex = $keys === [] ? '' : '/(?<=\s)('.implode('|', array_map(fn ($k) => preg_quote($k, '/').'(?:es|s)?', $keys)).')(?=\s)/u';
            $out[$slug] = ['regex' => $regex, 'lookup' => $lookup, 'specificity' => $specificity, 'arabic' => $arabic];
        }

        return self::$compiled = $out;
    }

    private function snippet(string $text, string $keyword): string
    {
        $pos = mb_stripos($text, $keyword);
        if ($pos === false) {
            return Str::limit($text, 80);
        }
        $start = max(0, $pos - 30);

        return ($start > 0 ? '…' : '').trim(mb_substr($text, $start, 80)).'…';
    }
}
