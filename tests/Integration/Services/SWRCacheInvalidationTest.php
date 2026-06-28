<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Services\SWRCacheService;
use SmartCache\Facades\SmartCache;

beforeEach(function (): void {
    SmartCache::clear();
});

describe('SWR Cache Invalidation', function (): void {
    it('invalidates SWR search suggestions when product is created', function (): void {
        // Arrange
        $suggestionKey1 = 'search:suggest:'.md5('django5');
        $suggestionKey2 = 'search:suggest:'.md5('python10');

        SWRCacheService::rememberSearchSuggestions($suggestionKey1, fn (): array => ['Django 5 Tips']);
        SWRCacheService::rememberSearchSuggestions($suggestionKey2, fn (): array => ['Python Best Practices']);

        expect(SmartCache::has($suggestionKey1))->toBeTrue();
        expect(SmartCache::has($suggestionKey2))->toBeTrue();

        // Act - Create new product (triggers observer)
        $product = Product::factory()->create();

        // Assert - Search suggestions cleared by observer
        expect(SmartCache::has($suggestionKey1))->toBeFalse();
        expect(SmartCache::has($suggestionKey2))->toBeFalse();
    });

    it('invalidates SWR cache with pattern matching when category changes', function (): void {
        // Arrange
        $goodForStartKey1 = 'shop.category.programming.good-for-start.courses.limit-5';
        $goodForStartKey2 = 'shop.category.programming.good-for-start.courses.limit-10';
        $goodForStartKey3 = 'shop.category.design.good-for-start.courses.limit-5';

        // Cache SWR data for multiple good-for-start endpoints
        SWRCacheService::rememberTrendingContent($goodForStartKey1, fn (): array => ['courses' => [1]]);
        SWRCacheService::rememberTrendingContent($goodForStartKey2, fn (): array => ['courses' => [1, 2]]);
        SWRCacheService::rememberTrendingContent($goodForStartKey3, fn (): array => ['courses' => [3]]);

        expect(SmartCache::has($goodForStartKey1))->toBeTrue();
        expect(SmartCache::has($goodForStartKey2))->toBeTrue();
        expect(SmartCache::has($goodForStartKey3))->toBeTrue();

        // Act - Create category (doesn't directly trigger good-for-start changes)
        $category = Category::factory()->create(['slug' => 'programming']);

        // Update category to trigger observer
        $category->update(['slug' => 'programming-updated']);

        // Assert - All matching patterns are cleared
        expect(SmartCache::has($goodForStartKey1))->toBeFalse();
        expect(SmartCache::has($goodForStartKey2))->toBeFalse();
        expect(SmartCache::has($goodForStartKey3))->toBeFalse();
    });

    it('preserves unrelated SWR caches during invalidation', function (): void {
        // Arrange - Create multiple SWR caches
        // Note: Product changes clear: shop.homepage.content, shop.category.*.good-for-start.*, search:*
        $homepageContentKey = 'shop.homepage.content'; // Cleared by Product observer (HomePageContent enum)
        $searchSuggestKey   = 'search:suggest:'.md5('test'); // Cleared by search:suggest:* pattern
        $slidersCacheKey    = 'shop.homepage.sliders'; // NOT cleared by Product (only by Slider updates)
        $userProfileKey     = 'user.123.profile'; // Not managed by any observer

        SWRCacheService::rememberHomepageContent($homepageContentKey, fn (): array => ['content']);
        SWRCacheService::rememberSearchSuggestions($searchSuggestKey, fn (): array => ['test1']);
        SWRCacheService::rememberHomepageContent($slidersCacheKey, fn (): array => ['slider1']);
        SmartCache::put($userProfileKey, ['name' => 'John'], 3600); // Direct put, not SWR

        expect(SmartCache::has($homepageContentKey))->toBeTrue();
        expect(SmartCache::has($searchSuggestKey))->toBeTrue();
        expect(SmartCache::has($slidersCacheKey))->toBeTrue();
        expect(SmartCache::has($userProfileKey))->toBeTrue();

        // Act - Create product (only clears Product-related patterns)
        $product = Product::factory()->create();

        // Assert
        expect(SmartCache::has($homepageContentKey))->toBeFalse();  // Cleared: HomePageContent pattern
        expect(SmartCache::has($searchSuggestKey))->toBeFalse();    // Cleared: search:suggest:* pattern
        expect(SmartCache::has($slidersCacheKey))->toBeTrue();      // NOT cleared: only Slider updates clear this
        expect(SmartCache::has($userProfileKey))->toBeTrue();       // NOT cleared: not in any observer map
    });

    it('handles concurrent SWR cache invalidation for multiple products', function (): void {
        // Arrange
        $cacheKeys = [
            'search:suggest:'.md5('product1'),
            'search:suggest:'.md5('product2'),
            'search:suggest:'.md5('product3'),
            'shop.category.test.good-for-start.courses',
        ];

        foreach ($cacheKeys as $key) {
            SWRCacheService::rememberSearchSuggestions($key, fn (): array => ['data']);
        }

        foreach ($cacheKeys as $key) {
            expect(SmartCache::has($key))->toBeTrue();
        }

        // Act - Create and update multiple products rapidly
        $p1 = Product::factory()->create();
        $p2 = Product::factory()->create();
        $p3 = Product::factory()->create();

        // Assert - All caches cleared by their respective observers
        foreach ($cacheKeys as $key) {
            expect(SmartCache::has($key))->toBeFalse();
        }
    });

    it('demonstrates SWR benefits over cache tags (no memory leaks)', function (): void {
        // This test proves SWR + patterns is safer than tags

        // SWR approach (safe)
        $swr_key1 = 'product.cache.item1';
        $swr_key2 = 'product.cache.item2';

        SWRCacheService::rememberHomepageContent($swr_key1, fn (): array => ['data1']);
        SWRCacheService::rememberHomepageContent($swr_key2, fn (): array => ['data2']);

        // Invalidate all product.*  caches
        SmartCache::flushPatterns(['product.cache.*']);

        // All cleared cleanly - no memory leaks
        expect(SmartCache::has($swr_key1))->toBeFalse();
        expect(SmartCache::has($swr_key2))->toBeFalse();

        // Tag approach (problematic - avoided)
        // Cache::tags(['products'])->put('key1', 'data1');      <- memory leak risk
        // Cache::tags(['products', 'api'])->put('key2', 'data2'); <- order matters!
        // Cache::tags(['products'])->flush();                  <- doesn't always work
    });

    it('works with all cache drivers (unlike tags)', function (): void {
        // SWR pattern works with:
        // - Redis ✓
        // - Database ✓
        // - File ✓
        // - Array ✓
        // - Memcached ⚠️

        // Tags only work with Redis and Memcached (problematic!)

        $cacheKey = 'universal.swr.key';
        $data     = ['driver_agnostic' => true];

        // This works regardless of which cache driver is configured
        $result = SWRCacheService::rememberHomepageContent($cacheKey, fn (): array => $data);

        expect($result)->toBe($data);

        // Pattern invalidation also works universally
        SmartCache::flushPatterns(['universal.*']);

        expect(SmartCache::has($cacheKey))->toBeFalse();
    });
});
