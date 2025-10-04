# Search DTO Architecture Simplification

## Overview

This document describes the refactoring from a dual-DTO pattern to a single-DTO pattern for the search system, improving code simplicity while maintaining type safety.

## Problem Identified

The original implementation used an **over-engineered dual DTO pattern**:

```
Request → SearchRequestData → GlobalSearchFilterData → GlobalSearchService
              ↓
    Manual extraction of 7 properties
```

**Issues:**
1. **Manual property extraction** - Error-prone and tedious
2. **Duplicate properties** - Same filter properties in two DTOs
3. **Maintenance burden** - Adding a new filter requires updating multiple places
4. **Unnecessary transformation** - Creating intermediate DTO with no additional validation or logic

## Solution: Single DTO Pattern

**New simplified architecture:**

```
Request → SearchData → GlobalSearchService
              ↓
         Direct usage
```

**Benefits:**
- ✅ **Less code** - Removed ~10 lines of manual property extraction
- ✅ **Single source of truth** - One DTO with all search parameters
- ✅ **Same type safety** - Full IDE support and type checking
- ✅ **Easier to extend** - Add property once, not in multiple places
- ✅ **Clearer intent** - No intermediate transformations to understand

## Changes Made

### 1. Renamed DTO Class

**Before:** `SearchRequestData.php`
**After:** `SearchData.php`

**Reason:** More general name since it's used throughout the search system, not just for requests.

### 2. Updated SearchController

**Before:**
```php
public function __invoke(SearchRequestData $requestData, GlobalSearchService $service, ...)
{
    // Manual property extraction
    $filters = GlobalSearchFilterData::from([
        'productable_type'  => $requestData->productable_type,
        'has_discount'      => $requestData->has_discount,
        'category_ids'      => $requestData->category_ids,
        'price_min'         => $requestData->price_min,
        'price_max'         => $requestData->price_max,
        'level'             => $requestData->level,
        'fulfillment_types' => $requestData->fulfillment_types,
    ]);
    
    $results = $service->search($requestData->q, $requestData->per_page, $filters);
```

**After:**
```php
public function __invoke(SearchData $searchData, GlobalSearchService $service, ...)
{
    // Pass SearchData DTO directly - no intermediate transformation!
    $results = $service->search($searchData);
```

**Improvement:** Removed 10 lines of manual property extraction, clearer intent.

### 3. Updated GlobalSearchService

**Before:**
```php
public function search(string $query, int $perPage = 15, ?GlobalSearchFilterData $filters = null): LengthAwarePaginator
{
    // Three separate parameters
    if ($this->isTypesenseAvailable()) {
        return $this->searchWithTypesense($query, $perPage, $filters);
    }
    return $this->searchWithDatabase($query, $perPage, $filters);
}
```

**After:**
```php
public function search(SearchData $searchData): LengthAwarePaginator
{
    // Single DTO parameter
    if ($this->isTypesenseAvailable()) {
        return $this->searchWithTypesense($searchData);
    }
    return $this->searchWithDatabase($searchData);
}
```

**Improvement:** Single parameter instead of three, better cohesion.

### 4. Updated All Internal Methods

All private methods now accept `SearchData` and use its properties directly:

```php
private function searchWithTypesense(SearchData $searchData): LengthAwarePaginator
{
    // Use properties directly
    $query = $searchData->q;
    $perPage = $searchData->per_page;
    $productFilters = $this->buildProductFilters($searchData);
    // ...
}

private function buildProductFilters(SearchData $searchData): string
{
    // Access filter properties directly
    if (!empty($searchData->productable_type)) {
        $baseFilters[] = "productable_type:={$searchData->productable_type}";
    }
    // ...
}
```

### 5. Deleted Redundant DTO

**Removed:** `GlobalSearchFilterData.php`

This DTO is no longer needed since `SearchData` contains all the necessary properties.

### 6. Updated All Tests

All tests now use `SearchData` instead of `GlobalSearchFilterData`:

```php
// Before
$filters = GlobalSearchFilterData::from(['price_min' => 100000]);
$result = $method->invoke($service, $filters);

// After
$searchData = SearchData::from(['q' => 'test', 'price_min' => 100000]);
$result = $method->invoke($service, $searchData);
```

## SearchData Structure

```php
final class SearchData extends Data
{
    public function __construct(
        // Search parameters
        public string $q,
        public ?int $per_page = 15,
        
        // Filter parameters
        public ?string $productable_type = null,
        public ?bool $has_discount = null,
        public ?array $category_ids = null,
        public ?int $price_min = null,
        public ?int $price_max = null,
        public ?string $level = null,
        public ?array $fulfillment_types = null,
    ) {}
    
    // ... validation rules
}
```

## Architecture Diagram

### Before (Dual DTO Pattern)

```
┌──────────┐
│ Request  │
└────┬─────┘
     │
     v
┌─────────────────────┐
│ SearchRequestData   │  (Has all properties)
└────┬────────────────┘
     │
     │ Manual Extraction (7 properties)
     │
     v
┌─────────────────────┐
│GlobalSearchFilterData│ (Subset of properties)
└────┬────────────────┘
     │
     v
┌─────────────────────┐
│ GlobalSearchService │
└─────────────────────┘
```

### After (Single DTO Pattern)

```
┌──────────┐
│ Request  │
└────┬─────┘
     │
     v
┌─────────────────────┐
│    SearchData       │  (Has all properties)
└────┬────────────────┘
     │
     │ Direct usage
     │
     v
┌─────────────────────┐
│ GlobalSearchService │
└─────────────────────┘
```

## Testing Results

All tests pass successfully:

```
✓ GlobalSearchService → it falls back to database when typesense is not configured
✓ GlobalSearchService → it builds product filters correctly
✓ GlobalSearchService → it builds blog filters correctly
✓ GlobalSearchService → it handles price_min only filter
✓ GlobalSearchService → it handles price_max only filter
✓ SearchController → it requires search query parameter
✓ SearchController → it rejects per_page parameter over maximum
✓ SearchController → it accepts valid search request with all filter parameters
... (19 tests passed)
```

## Files Modified

1. **Renamed:**
   - `app/Data/Shop/Search/SearchRequestData.php` → `SearchData.php`

2. **Updated:**
   - `app/Http/Controllers/Api/Shop/SearchController.php`
   - `app/Services/GlobalSearchService.php`
   - `tests/Feature/Shop/SearchTest.php`

3. **Deleted:**
   - `app/Data/Shop/Search/GlobalSearchFilterData.php`

## Key Takeaways

1. **YAGNI Principle** - "You Aren't Gonna Need It" - The dual DTO pattern added complexity without providing value
2. **Single Source of Truth** - Having one DTO with all properties is simpler and easier to maintain
3. **Type Safety Preserved** - Simplification didn't sacrifice type safety or IDE support
4. **Easy to Extend** - Adding new filters now requires updating only one place (SearchData)

## Migration Guide for Developers

If you need to add a new filter to the search system:

**Before (Old Pattern):**
1. Add property to `SearchRequestData`
2. Add property to `GlobalSearchFilterData`
3. Add manual extraction in `SearchController`
4. Use in `GlobalSearchService`

**After (New Pattern):**
1. Add property to `SearchData`
2. Use directly in `GlobalSearchService`

**That's it!** 2 steps instead of 4.

## Conclusion

This refactoring demonstrates that **simpler is often better**. By eliminating the unnecessary intermediate DTO, we:
- Reduced code complexity
- Improved maintainability
- Maintained all functionality and type safety
- Made the codebase easier for future developers to understand

The lesson: Always question if intermediate transformations add real value, or if they're just adding complexity.
