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

    /**
     * The best category for each business, chosen only from the given taxonomy.
     *
     * @param  list<array{id: int, name: ?string, name_ar: ?string, labels: list<string>, services: ?string, description: ?string, current: ?string}>  $businesses
     * @param  array<string, string>  $taxonomy  slug => "Parent › Category"
     * @return array<int, array{category: string, secondary: list<string>, confidence: int, reason_en: string, reason_ar: string}> id => verdict
     */
    public function categorize(array $businesses, array $taxonomy): array;
}
