<?php

declare(strict_types=1);

return [
    // Max number of vector (semantic) hits the fallback search can add.
    'vector_k' => (int) env('SEARCH_VECTOR_K', 6),

    // Vector hits farther than this are dropped (rejects nonsense queries).
    'vector_distance_threshold' => (float) env('SEARCH_VECTOR_DISTANCE_THRESHOLD', 0.15),

    // Retry a zero-hit semantic query slightly more loosely.
    'vector_retry_distance_threshold' => (float) env('SEARCH_VECTOR_RETRY_DISTANCE_THRESHOLD', 0.16),

    // If strict keyword search finds fewer results than this, run semantic search.
    'min_keyword_results' => (int) env('SEARCH_MIN_KEYWORD_RESULTS', 1),
];
