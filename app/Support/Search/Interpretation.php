<?php

declare(strict_types=1);

namespace App\Support\Search;

use App\Models\Area;
use App\Models\Category;
use App\Models\Municipality;

/** What a plain-language search was understood to mean. */
final class Interpretation
{
    public function __construct(
        public readonly ?Category $category = null,
        public readonly ?Municipality $municipality = null,
        public readonly ?Area $area = null,
        public readonly bool $openNow = false,
        public readonly bool $verified = false,
        public readonly ?string $remainder = null,
        public readonly string $source = 'rules',
    ) {}

    public function understood(): bool
    {
        return $this->category !== null || $this->municipality !== null || $this->area !== null || $this->openNow || $this->verified;
    }

    /** Query string for the search page with the understood filters. */
    public function toQuery(string $original): array
    {
        return array_filter([
            'q' => $this->remainder,
            'category' => $this->category?->slug,
            'municipality' => $this->municipality?->slug,
            'area' => $this->area?->slug,
            'open_now' => $this->openNow ? 1 : null,
            'verified' => $this->verified ? 1 : null,
            'understood' => $original,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
