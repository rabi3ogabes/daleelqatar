<?php

declare(strict_types=1);

namespace App\Support\Import;

use App\Support\Categories\CategoryClassifier;

/**
 * Maps free-text categories from other directories ("Car Rental Companies",
 * "Typing, Translation, Attestation & PRO Services" …) onto this directory's
 * taxonomy. The weighing itself lives in the CategoryClassifier, so imports,
 * the admin's category review and new listings all agree.
 */
final class CategoryMatcher
{
    public const FALLBACK = 'other-businesses';

    public function __construct(private readonly CategoryClassifier $classifier) {}

    /**
     * @param  list<string>  $categories  source category labels
     * @param  list<string>  $tags
     * @return list<int> category ids, most relevant first (never empty)
     */
    public function match(array $categories, array $tags = [], ?string $name = null, array $extra = []): array
    {
        $ranking = $this->classifier->rank(['labels' => [...$categories, ...$tags], 'name' => $name] + $extra);
        $verdict = $this->classifier->verdict($ranking, null);

        $ids = [];
        foreach (array_filter([$verdict['category'], ...$verdict['secondary']]) as $slug) {
            if (($id = $this->id($slug)) !== null) {
                $ids[] = $id;
            }
        }

        return $ids ?: array_filter([$this->id(self::FALLBACK)]);
    }

    public function isFallback(int $categoryId): bool
    {
        return $categoryId === $this->id(self::FALLBACK);
    }

    public function slugFor(string $label): ?string
    {
        return $this->classifier->verdict($this->classifier->rank(['labels' => [$label]]), null)['category'];
    }

    public function id(string $slug): ?int
    {
        return $this->classifier->category($slug)['id'] ?? null;
    }
}
