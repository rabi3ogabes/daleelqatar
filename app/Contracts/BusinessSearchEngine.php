<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Support\Search\SearchCriteria;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Directory search abstraction. The default implementation uses PostgreSQL
 * full-text and trigram indexes; a dedicated engine (Meilisearch, Typesense,
 * OpenSearch…) can be bound instead without touching controllers or the API.
 */
interface BusinessSearchEngine
{
    public function search(SearchCriteria $criteria): LengthAwarePaginator;
}
