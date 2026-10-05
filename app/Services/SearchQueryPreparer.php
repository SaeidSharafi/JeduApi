<?php

declare(strict_types=1);

namespace App\Services;

final class SearchQueryPreparer
{
    public function forKeywordSearch(?string $query): string
    {
        $query = mb_trim((string) $query);
        $query = preg_replace('/\s+[-‐‑‒–—―−]\s+/u', ' ', $query) ?? $query;

        return preg_replace('/\s+/u', ' ', $query) ?? $query;
    }

    public function forSemanticSearch(?string $query, bool $reverseTerms = false): string
    {
        $terms = preg_split('/\s+/u', $this->forKeywordSearch($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($reverseTerms) {
            $terms = array_reverse($terms);
        }

        return implode(' ', $terms);
    }
}
