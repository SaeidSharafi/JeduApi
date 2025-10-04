# Search Fallback Implementation

## Overview

This document describes the smart fallback pattern implemented in the JeduShop search system to ensure search functionality works regardless of whether Typesense is available or not.

## Problem Statement

The application uses Typesense for advanced search capabilities (semantic search with embeddings, typo tolerance, faceted search). However, some deployment servers may not have Typesense installed, requiring a fallback mechanism to ensure search always works.

## Solution: Smart Fallback Pattern

Both `ProductQueryService` and `GlobalSearchService` now implement automatic Typesense detection with graceful degradation to database search.

### Key Components

#### 1. Typesense Availability Detection

Both services include an `isTypesenseAvailable()` helper method:

```php
private function isTypesenseAvailable(): bool
{
    static $available = null;
    if ($available === null) {
        $available = config('scout.driver') === 'typesense'
            && !empty(config('scout.typesense.client-settings.api_key'))
            && !app()->runningUnitTests();
    }
    return $available;
}
```

**Detection Criteria:**
- Scout driver must be set to 'typesense'
- Typesense API key must be configured
- Not running in unit test environment
- Result is cached statically to avoid repeated config checks

#### 2. ProductQueryService Smart Search

The `ProductQueryService` now includes a `globalSearch()` method that intelligently routes to the appropriate search implementation:

```php
public function globalSearch(ProductListRequestData $requestData): LengthAwarePaginator
{
    if ($this->isTypesenseAvailable()) {
        try {
            return $this->globalSearchProductsScout($requestData);
        } catch (Exception $e) {
            Log::warning('Typesense search failed, falling back to database', [
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    return $this->globalSearchProductsDatabase($requestData);
}
```

**Flow:**
1. Check if Typesense is available
2. If yes, try Typesense search
3. On any exception, log warning and fall back to database
4. If Typesense not available, use database directly

#### 3. GlobalSearchService Smart Search

The `GlobalSearchService.search()` method has been refactored into three methods:

```php
public function search(string $query, int $perPage = 15, array $filters = []): LengthAwarePaginator
{
    if ($this->isTypesenseAvailable()) {
        try {
            return $this->searchWithTypesense($query, $perPage, $filters);
        } catch (Exception $e) {
            Log::warning('Typesense search failed, falling back to database', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    return $this->searchWithDatabase($query, $perPage, $filters);
}
```

**`searchWithTypesense()` Method:**
- Handles union search across Product and BlogPost collections
- Uses hybrid semantic + keyword search
- Implements caching (10 minutes)
- Logs analytics data
- Supports faceted filters

**`searchWithDatabase()` Method:**
- Converts filters array to ProductFilterData DTO
- Creates ProductListRequestData for ProductQueryService
- Searches products using `globalSearchProductsDatabase()`
- Searches blog posts using MySQL LIKE queries
- Merges and paginates results from both sources

```php
private function searchWithDatabase(string $query, int $perPage, array $filters): LengthAwarePaginator
{
    // Convert filters to DTO
    $productFilterData = new ProductFilterData(
        category_ids: $filters['category_ids'] ?? null,
        type: $filters['productable_type'] ?? null,
        min_price: $filters['price_min'] ?? null,
        max_price: $filters['price_max'] ?? null,
        with_discounts: $filters['has_discount'] ?? null,
    );
    
    $productRequestData = new ProductListRequestData(
        filter: $productFilterData,
        search: $query,
        per_page: $perPage,
        page: $page,
    );
    
    // Search products
    $productQueryService = app(ProductQueryService::class);
    $products = $productQueryService->globalSearchProductsDatabase($productRequestData);
    
    // Search blog posts
    $blogQuery = BlogPost::query()
        ->where('status', PublicationStatusEnum::PUBLISHED)
        ->where(function ($q) use ($query) {
            $q->where('title', 'like', "%{$query}%")
                ->orWhere('body', 'like', "%{$query}%")
                ->orWhere('excerpt', 'like', "%{$query}%");
        });
    
    if (!empty($filters['category_ids'])) {
        $blogQuery->whereHas('categories', function ($q) use ($filters) {
            $q->whereIn('blog_categories.id', $filters['category_ids']);
        });
    }
    
    $blogPosts = $blogQuery->paginate($perPage, ['*'], 'page', $page);
    
    // Merge results
    $allResults = $products->merge($blogPosts);
    $totalResults = $products->total() + $blogPosts->total();
    
    return new LengthAwarePaginator($allResults, $totalResults, $perPage, $page);
}
```

### Controller Updates

**ProductSearchController** now uses the smart search method:

```php
// Before
$results = $this->productQueryService
    ->globalSearchProductsScout($requestData);

// After
$results = $this->productQueryService
    ->globalSearch($requestData);
```

This single line change enables automatic fallback for all product search endpoints.

## Architecture Benefits

### 1. Reliability
- Search always works, regardless of Typesense availability
- Graceful degradation on errors
- No breaking changes to API contracts

### 2. Performance
- Static caching of availability check reduces overhead
- Database queries optimized with indexes
- Typesense caching preserved (10 min for searches, 1 hour for suggestions)

### 3. Maintainability
- Clean separation of concerns
- Consistent DTO patterns across services
- Comprehensive logging for debugging
- Single source of truth for availability logic

### 4. Flexibility
- Easy to switch between Typesense and database via config
- Can be controlled per environment
- Supports A/B testing and gradual rollout

## Configuration

### Enable Typesense (config/scout.php)
```php
'driver' => env('SCOUT_DRIVER', 'typesense'),

'typesense' => [
    'client-settings' => [
        'api_key' => env('TYPESENSE_API_KEY', ''),
        'nodes' => [
            [
                'host' => env('TYPESENSE_HOST', 'localhost'),
                'port' => env('TYPESENSE_PORT', '8108'),
                'protocol' => env('TYPESENSE_PROTOCOL', 'http'),
            ],
        ],
    ],
],
```

### Use Database Fallback (.env)
```
SCOUT_DRIVER=database
```

Or remove/comment out:
```
# TYPESENSE_API_KEY=
```

## Testing

### Fallback Test
```php
it('falls back to database when typesense is not configured', function () {
    // Force database driver
    Config::set('scout.driver', 'database');
    
    $service = new GlobalSearchService();
    $result = $service->search('test query', 15, []);
    
    expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
});
```

### Test Results
- 19 tests passing
- 1 test skipped (requires Typesense server)
- All validation and filter tests passing
- Database fallback verified

## Logging

### Typesense Search
```
[info] Search executed via Typesense
    query: "laravel course"
    results_count: 42
    filters: {"category_ids": [1, 2], "has_discount": true}
    user_id: 123
```

### Database Fallback
```
[info] Using database fallback for search
    query: "laravel course"
    filters: {"category_ids": [1, 2], "has_discount": true}

[warning] Typesense search failed, falling back to database
    query: "laravel course"
    error: "Connection refused"
```

## Migration Guide

### For Existing Deployments

1. **No code changes required** - fallback is automatic
2. Update `.env` if needed:
   ```
   SCOUT_DRIVER=typesense  # or 'database'
   TYPESENSE_API_KEY=your_api_key_here
   ```
3. Run tests to verify: `sail artisan test tests/Feature/Shop/SearchTest.php`

### For New Deployments

1. **Without Typesense:**
   ```
   SCOUT_DRIVER=database
   ```

2. **With Typesense:**
   ```
   SCOUT_DRIVER=typesense
   TYPESENSE_API_KEY=your_api_key
   TYPESENSE_HOST=localhost
   TYPESENSE_PORT=8108
   TYPESENSE_PROTOCOL=http
   ```

## Performance Comparison

| Feature | Typesense | Database |
|---------|-----------|----------|
| Semantic Search | ✅ Yes | ❌ No |
| Typo Tolerance | ✅ Yes | ❌ No |
| Prefix Search | ✅ Yes | ⚠️ LIKE queries |
| Faceted Filters | ✅ Native | ⚠️ Subqueries |
| Speed (large dataset) | ✅ Fast | ⚠️ Slower |
| Cost | 💰 Requires server | ✅ Free |
| Setup | 🔧 Complex | ✅ Simple |
| Reliability | ⚠️ External service | ✅ Always available |

## Best Practices

1. **Use Typesense in production** when possible for best search quality
2. **Keep database fallback** for reliability and development environments
3. **Monitor logs** for fallback frequency - high rates indicate Typesense issues
4. **Test both paths** when making search-related changes
5. **Use DTOs consistently** for filter data across all services

## Future Enhancements

1. **Enhanced Database Search:**
   - Add MySQL full-text search indexes
   - Implement relevance scoring
   - Add faceted filter aggregations

2. **Monitoring:**
   - Track fallback frequency
   - Alert on Typesense failures
   - Performance metrics comparison

3. **Cache Warming:**
   - Pre-cache popular searches
   - Background refresh for stale cache

4. **Progressive Enhancement:**
   - Start with database, upgrade to Typesense
   - A/B testing framework
   - Gradual rollout per user segment

## Related Documentation

- [Search Implementation Improvements](./Search-Implementation-Improvements.md)
- [Search Architecture Analysis](./Search-Architecture-Analysis.md)
- [Search Architecture Revised](./Search-Architecture-Revised.md)
- [Backend Developer Guide - Order and Discount System](./Backend-Developer-Guide-Order-and-Discount-System.md)
