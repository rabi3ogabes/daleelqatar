<?php

declare(strict_types=1);

namespace App\Support\Enrichment;

/** Writing assistant behind the enrichment suggestions (every result is reviewed by an admin). */
interface AiWriter
{
    public function available(): bool;

    /**
     * Arabic names for businesses listed under an English name.
     *
     * @param  list<array{id: int, name: string, category: ?string, location: ?string}>  $businesses
     * @return array<int, string> id => Arabic name
     */
    public function arabicNames(array $businesses): array;

    /**
     * A factual profile written only from the company's own website text.
     *
     * @param  array<string,string>  $pages  url => page text
     * @return array{found: bool, description_en: ?string, description_ar: ?string, tagline_en: ?string, tagline_ar: ?string, services: list<array{name_en: string, name_ar: string}>}
     */
    public function profileFromWebsite(string $name, ?string $category, array $pages): array;
}
