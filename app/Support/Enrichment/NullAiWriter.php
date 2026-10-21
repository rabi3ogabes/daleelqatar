<?php

declare(strict_types=1);

namespace App\Support\Enrichment;

/** Used when no AI key is configured: enrichment still runs, without AI suggestions. */
final class NullAiWriter implements AiWriter
{
    public function available(): bool
    {
        return false;
    }

    public function arabicNames(array $businesses): array
    {
        return [];
    }

    public function profileFromWebsite(string $name, ?string $category, array $pages): array
    {
        return ['found' => false, 'description_en' => null, 'description_ar' => null, 'tagline_en' => null, 'tagline_ar' => null, 'services' => []];
    }
}
