<?php

declare(strict_types=1);

namespace App\Support\Search;

/** Optional AI step of smart search: maps a sentence to known category and place slugs only. */
interface SearchInterpreter
{
    public function available(): bool;

    /**
     * @param  array<string, string>  $taxonomy  category slug => label
     * @param  array<string, string>  $places  "m:slug"/"a:slug" => name
     * @return array{category?: ?string, municipality?: ?string, area?: ?string, open_now?: bool, keywords?: ?string}|null
     */
    public function interpret(string $query, array $taxonomy, array $places): ?array;
}
