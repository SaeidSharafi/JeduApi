<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CustomValidationException;
use App\Models\Blog\BlogPost;
use App\Models\Product;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\EngineManager;

final class GlobalSearchService
{
    /**
     * @var \Laravel\Scout\Engines\TypesenseEngine
     */
    private $typesenseEngine;

    public function __construct(EngineManager $engineManager)
    {
        $this->typesenseEngine = $engineManager->engine();
    }

    /**
     * Perform a multi-model search using Typesense.
     *
     * @param  string  $query  The search term.
     * @param  int  $perPage  The number of results per page (applied to combined results across all collections).
     */
    public function search(string $query, int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $page     = LengthAwarePaginator::resolveCurrentPage();
        $cacheKey = 'search:'.md5($query.json_encode($filters).$perPage.$page);

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($query, $perPage, $filters, $page) {
            try {
                $productFilters = $this->buildProductFilters($filters);
                $blogFilters    = $this->buildBlogFilters($filters);

                $prefix        = config('scout.prefix', '');
                $productIndex  = $prefix.(new Product())->searchableAs();
                $blogPostIndex = $prefix.(new BlogPost())->searchableAs();

                $searchRequests = [
                    'union'    => true,
                    'page'     => $page,
                    'per_page' => $perPage,
                    'searches' => [
                        [
                            'collection'            => $productIndex,
                            'q'                     => $query,
                            'query_by'              => 'embedding, name, short_name, productable_full_name, productable_short_name, short_description, productable_description',
                            'query_by_weights'      => '0, 10, 8, 8, 5, 2, 4',
                            'rerank_hybrid_matches' => true,
                            'vector_query'          => 'embedding:([], alpha: 0.4)',
                            'include_fields'        => 'id',
                            'sort_by'               => '_text_match:desc,created_at:desc',
                            'filter_by'             => $productFilters,
                            'facet_by'              => 'productable_type,has_discount,category_slugs,level,fulfillment_types',
                            'max_facet_values'      => 100,
                        ],
                        [
                            'collection'            => $blogPostIndex,
                            'q'                     => $query,
                            'query_by'              => 'embedding, title, body, excerpt',
                            'query_by_weights'      => '0, 10, 5, 2',
                            'rerank_hybrid_matches' => true,
                            'vector_query'          => 'embedding:([], alpha: 0.4)',
                            'sort_by'               => '_text_match:desc,created_at:desc',
                            'include_fields'        => 'id',
                            'filter_by'             => $blogFilters,
                        ],
                    ],
                ];

                $multiSearch = $this->typesenseEngine->getMultiSearch();
                $rawResults  = $multiSearch->perform($searchRequests);

                // Check for errors
                if (isset($rawResults['error'])) {
                    throw new CustomValidationException('Typesense multi-search error: '.($rawResults['error'] ?? 'Unknown error'));
                }

                $hits      = $rawResults['hits']  ?? [];
                $totalHits = $rawResults['found'] ?? 0;

                // Log search analytics
                Log::channel('daily')->info('Search performed', [
                    'query'         => $query,
                    'results_count' => $totalHits,
                    'filters'       => $filters,
                    'user_id'       => auth()->id(),
                ]);

                $models = $this->hydrateModels($hits);

                return new LengthAwarePaginator(
                    $models,
                    $totalHits,
                    $perPage,
                    $page
                );
            } catch (Exception $e) {
                Log::error('Typesense search failed', [
                    'query'   => $query,
                    'filters' => $filters,
                    'error'   => $e->getMessage(),
                    'trace'   => $e->getTraceAsString(),
                ]);

                // Return empty results as fallback
                return new LengthAwarePaginator(collect(), 0, $perPage, $page);
            }
        });
    }

    /**
     * Get search suggestions for autocomplete.
     *
     * @param  string  $query  The search term prefix.
     * @param  int  $limit  Maximum number of suggestions.
     */
    public function suggest(string $query, int $limit = 5): array
    {
        $cacheKey = 'search:suggest:'.md5($query.$limit);

        return Cache::remember($cacheKey, now()->addHours(1), function () use ($query, $limit) {
            try {
                $prefix       = config('scout.prefix', '');
                $productIndex = $prefix.(new Product())->searchableAs();

                $client  = $this->typesenseEngine->getTTypesenseClient();
                $results = $client->collections[$productIndex]->documents->search([
                    'q'         => $query,
                    'query_by'  => 'name,short_name,productable_full_name',
                    'per_page'  => $limit * 2,
                    'prefix'    => 'true',
                    'filter_by' => 'status:=published && is_visible:=true',
                ]);

                $suggestions = collect($results['hits'] ?? [])
                    ->pluck('document.name')
                    ->unique()
                    ->take($limit)
                    ->values()
                    ->all();

                return $suggestions;
            } catch (Exception $e) {
                Log::error('Typesense suggest failed', [
                    'query' => $query,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Hydrate Eloquent models from Typesense union search results.
     *
     * With union=true, Typesense merges results from all collections by relevance.
     * We use the '_collection_type' field in each document to identify the model type.
     * Order from Typesense is preserved exactly as returned.
     *
     * @param  array  $hits  Array of hits from Typesense union search
     */
    private function hydrateModels(array $hits): Collection
    {
        $productIds  = [];
        $blogPostIds = [];

        // Group IDs by model type, preserving order with index.
        $orderedHits = [];
        foreach ($hits as $index => $hit) {
            $id             = $hit['document']['id'] ?? null;
            $collectionType = $hit['collection']     ?? null;
            if (! $id || ! $collectionType) {
                continue;
            }

            // Map collection type to model type
            if ($collectionType === 'products') {
                $productIds[]        = $id;
                $orderedHits[$index] = ['type' => 'product', 'id' => $id];
            } elseif ($collectionType === 'blog_posts') {
                $blogPostIds[]       = $id;
                $orderedHits[$index] = ['type' => 'blog_post', 'id' => $id];
            }
        }

        // Eager-load all models in just two queries.
        $products  = ! empty($productIds) ? Product::whereIn('id', $productIds)->get()->keyBy('id') : collect();
        $blogPosts = ! empty($blogPostIds) ? BlogPost::whereIn('id', $blogPostIds)->get()->keyBy('id') : collect();

        // Reconstruct the collection in the EXACT order from Typesense results.
        $models = collect();
        foreach ($orderedHits as $hit) {
            $modelId = $hit['id'];

            if ($hit['type'] === 'product' && $products->has($modelId)) {
                $models->push($products->get($modelId));
            } elseif ($hit['type'] === 'blog_post' && $blogPosts->has($modelId)) {
                $models->push($blogPosts->get($modelId));
            }
        }

        return $models;
    }

    private function buildProductFilters(array $filters): string
    {
        $baseFilters = ['status:=published', 'is_visible:=true'];

        if (! empty($filters['productable_type'])) {
            $baseFilters[] = "productable_type:={$filters['productable_type']}";
        }

        if (isset($filters['has_discount'])) {
            $value         = $filters['has_discount'] ? 'true' : 'false';
            $baseFilters[] = "has_discount:={$value}";
        }

        if (! empty($filters['category_ids'])) {
            $ids           = implode(',', $filters['category_ids']);
            $baseFilters[] = "category_ids:=[{$ids}]";
        }

        if (isset($filters['price_min']) && isset($filters['price_max'])) {
            $baseFilters[] = "price:[{$filters['price_min']}..{$filters['price_max']}]";
        } elseif (isset($filters['price_min'])) {
            $baseFilters[] = "price:>={$filters['price_min']}";
        } elseif (isset($filters['price_max'])) {
            $baseFilters[] = "price:<={$filters['price_max']}";
        }

        if (! empty($filters['level'])) {
            $baseFilters[] = "level:={$filters['level']}";
        }

        if (! empty($filters['fulfillment_types'])) {
            $types         = array_map(fn ($type) => "fulfillment_types:={$type}", $filters['fulfillment_types']);
            $baseFilters[] = '('.implode(' || ', $types).')';
        }

        return implode(' && ', $baseFilters);
    }

    private function buildBlogFilters(array $filters): string
    {
        $baseFilters = ['status:=published'];

        return implode(' && ', $baseFilters);
    }
}
