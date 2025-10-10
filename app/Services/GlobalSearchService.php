<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Shop\Product\Course\ProductFilterData;
use App\Data\Shop\Product\Course\ProductListRequestData;
use App\Data\Shop\Search\SearchData;
use App\Enums\Content\PublicationStatusEnum;
use App\Exceptions\CustomValidationException;
use App\Models\Blog\BlogPost;
use App\Models\Product;
use App\Query\ProductQueryService;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\EngineManager;
use SmartCache\Facades\SmartCache;

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
     * Perform a multi-model search with automatic fallback.
     * Uses Typesense if available, falls back to database search.
     *
     * @param  SearchData  $searchData  The search request containing query, pagination, and filters.
     */
    public function search(SearchData $searchData): LengthAwarePaginator
    {
        // @codeCoverageIgnoreStart
        if ($this->isTypesenseAvailable()) {
            try {
                return $this->searchWithTypesense($searchData);
            } catch (Exception $e) {
                Log::warning('Typesense multi-search failed, falling back to database', [
                    'query'   => $searchData->q,
                    'filters' => $searchData->toArray(),
                    'error'   => $e->getMessage(),
                ]);
                // Fall through to database search
            }
        }

        // @codeCoverageIgnoreEnd
        return $this->searchWithDatabase($searchData);
    }

    /**
     * Get search suggestions for autocomplete.
     *
     * @param  string  $query  The search term prefix.
     * @param  int  $limit  Maximum number of suggestions.
     *
     * @codeCoverageIgnore we cannot reliably test caching behavior; tested via integration tests
     */
    public function suggest(string $query, int $limit = 5): array
    {
        if (! $this->isTypesenseAvailable()) {
            return [];
        }

        $cacheKey = 'search:suggest:'.md5($query.$limit);

        return SmartCache::remember($cacheKey, now()->addHour(), function () use ($query, $limit) {
            try {
                $results = Product::search($query)
                    ->where('status', PublicationStatusEnum::PUBLISHED->value)
                    ->where('is_visible', true)
                    ->take($limit * 2)->get();

                return $results
                    ?->pluck('name')
                    ->unique()
                    ->take($limit)
                    ->values()
                    ->all();
            } // @codeCoverageIgnoreStart
            catch (Exception $e) {
                Log::error('Typesense suggest failed', [
                    'query' => $query,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
            // @codeCoverageIgnoreEnd
        });
    }

    /**
     * Perform Typesense-based search with caching.
     *
     * @codeCoverageIgnore Requires real Typesense instance; tested via integration tests
     */
    private function searchWithTypesense(SearchData $searchData): LengthAwarePaginator
    {
        $page     = LengthAwarePaginator::resolveCurrentPage();
        $cacheKey = 'search:'.md5($searchData->q.json_encode($searchData->toArray()).$searchData->per_page.$page);

        return SmartCache::remember($cacheKey, now()->addMinutes(10), function () use ($searchData, $page) {
            $productFilters = $this->buildProductFilters($searchData);
            $blogFilters    = $this->buildBlogFilters($searchData);

            $prefix        = config('scout.prefix', '');
            $productIndex  = $prefix.(new Product())->searchableAs();
            $blogPostIndex = $prefix.(new BlogPost())->searchableAs();

            // Determine which types to search based on result_types filter
            $searchProducts  = empty($searchData->result_types) || in_array('product', $searchData->result_types);
            $searchBlogPosts = empty($searchData->result_types) || in_array('blog_post', $searchData->result_types);
            $searches        = [];
            if ($searchProducts) {
                $searches[] = [
                    'collection'            => $productIndex,
                    'q'                     => $searchData->q,
                    'query_by'              => 'embedding, name, short_name, productable_full_name, productable_short_name, short_description, productable_description',
                    'query_by_weights'      => '0, 10, 8, 8, 5, 2, 4',
                    'rerank_hybrid_matches' => true,
                    'vector_query'          => 'embedding:([], alpha: 0.4)',
                    'include_fields'        => 'id',
                    'sort_by'               => '_text_match:desc,created_at:desc',
                    'filter_by'             => $productFilters,
                    'facet_by'              => 'productable_type,has_discount,category_slugs,level,fulfillment_types',
                    'max_facet_values'      => 100,
                ];
            }

            if ($searchBlogPosts) {
                $searches[] = [
                    'collection'            => $blogPostIndex,
                    'q'                     => $searchData->q,
                    'query_by'              => 'embedding, title, body, excerpt',
                    'query_by_weights'      => '0, 10, 5, 2',
                    'rerank_hybrid_matches' => true,
                    'vector_query'          => 'embedding:([], alpha: 0.4)',
                    'sort_by'               => '_text_match:desc,created_at:desc',
                    'include_fields'        => 'id',
                    'filter_by'             => $blogFilters,
                ];
            }

            $searchRequests = [
                'union'    => true,
                'page'     => $page,
                'per_page' => $searchData->per_page,
                'searches' => $searches,
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
                'query'         => $searchData->q,
                'results_count' => $totalHits,
                'filters'       => $searchData->toArray(),
                'user_id'       => auth()->id(),
            ]);

            $models = $this->hydrateModels($hits);

            return new LengthAwarePaginator(
                $models,
                $totalHits,
                $searchData->per_page,
                $page
            );
        });
    }

    /**
     * Perform database-based search as fallback.
     */
    private function searchWithDatabase(SearchData $searchData): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        // Determine which types to search
        $searchProducts = empty($searchData->result_types) || in_array('product', $searchData->result_types);
        if (! $searchProducts) {
            return BlogPost::query()
                ->where('status', PublicationStatusEnum::PUBLISHED)
                ->where(function ($q) use ($searchData) {
                    $q->fullTextSearch(['title', 'body', 'slug', 'excerpt'], $searchData->q);
                })->paginate()
                ->withQueryString();
        }

        // Search products using ProductQueryService with database
        $productFilterData = new ProductFilterData(
            category_ids: $searchData->category_ids,
            type: $searchData->productable_type,
            fulfillment_types: $searchData->fulfillment_types,
            level: $searchData->level,
            min_price: $searchData->price_min,
            max_price: $searchData->price_max,
            with_discounts: $searchData->has_discount,
        );

        $productRequestData = new ProductListRequestData(
            filter: $productFilterData,
            search: $searchData->q,
            page: $page,
            per_page: $searchData->per_page,
        );

        $productQueryService = app(ProductQueryService::class);
        $products            = $productQueryService->globalSearchProductsDatabase($productRequestData);

        return $products;
    }

    /**
     * Hydrate Eloquent models from Typesense union search results.
     *
     * With union=true, Typesense merges results from all collections by relevance.
     * We use the '_collection_type' field in each document to identify the model type.
     * Order from Typesense is preserved exactly as returned.
     *
     * @param  array  $hits  Array of hits from Typesense union search
     *
     * @codeCoverageIgnore Called only by searchWithTypesense; tested via integration tests
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

        // Eager-load all models in just two queries with required relationships.
        $products = ! empty($productIds)
            ? Product::whereIn('id', $productIds)
                ->with([
                    'vendor:id,name',
                    'categories:id,name,slug',
                    'productDeliveryOptions' => function ($q) {
                        $q->where('status', PublicationStatusEnum::PUBLISHED)
                            ->with(['productDeliveryOptionDiscountPrice', 'teachers:id,first_name,last_name,gender']);
                    },
                    'productable',
                ])
                ->get()
                ->keyBy('id')
            : collect();
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

    private function buildProductFilters(SearchData $searchData): string
    {
        $baseFilters = ['status:=published', 'is_visible:=true'];

        if (! empty($searchData->productable_type)) {
            $baseFilters[] = "productable_type:={$searchData->productable_type}";
        }

        if ($searchData->has_discount !== null) {
            $value         = $searchData->has_discount ? 'true' : 'false';
            $baseFilters[] = "has_discount:={$value}";
        }

        if (! empty($searchData->category_ids)) {
            $ids           = implode(',', $searchData->category_ids);
            $baseFilters[] = "category_ids:=[{$ids}]";
        }

        if ($searchData->price_min !== null && $searchData->price_max !== null) {
            $baseFilters[] = "price:[{$searchData->price_min}..{$searchData->price_max}]";
        } elseif ($searchData->price_min !== null) {
            $baseFilters[] = "price:>={$searchData->price_min}";
        } elseif ($searchData->price_max !== null) {
            $baseFilters[] = "price:<={$searchData->price_max}";
        }

        if (! empty($searchData->level)) {
            $baseFilters[] = "level:={$searchData->level}";
        }

        if (! empty($searchData->fulfillment_types)) {
            $types         = array_map(fn ($type) => "fulfillment_types:={$type}", $searchData->fulfillment_types);
            $baseFilters[] = '('.implode(' || ', $types).')';
        }

        return implode(' && ', $baseFilters);
    }

    private function buildBlogFilters(SearchData $searchData): string
    {
        $baseFilters = ['status:=published'];

        // Future: Add blog-specific filters here if needed
        // For now, blog posts only filter by published status

        return implode(' && ', $baseFilters);
    }

    /**
     * Check if Typesense is configured and available.
     */
    private function isTypesenseAvailable(): bool
    {
        static $available = null;

        if ($available === null) {
            $available = config('scout.driver') === 'typesense'
                && ! empty(config('scout.typesense.client-settings.api_key'));
        }

        return $available;
    }
}
