<?php

declare(strict_types=1);

use App\Models\Product;
use SmartCache\Facades\SmartCache;

beforeEach(function () {
    SmartCache::clear();
});

describe('GlobalSearchService Cache Invalidation', function () {
    it('clears search cache when product is updated', function () {
        // Arrange - Simulate GlobalSearchService MD5 hash-based cache keys
        $searchQuery = 'php programming';
        $filters     = ['category' => 'programming', 'level' => 'intermediate'];
        $perPage     = 20;
        $page        = 1;

        $cacheKey = 'search:'.md5($searchQuery.json_encode($filters).$perPage.$page);

        SmartCache::put($cacheKey, [
            'results' => [
                ['id' => 1, 'name' => 'PHP Basics'],
                ['id' => 2, 'name' => 'Advanced PHP'],
            ],
            'total' => 2,
        ], 600);

        expect(SmartCache::has($cacheKey))->toBeTrue();

        // Act - Update a product
        $product = Product::factory()->create();

        // Assert - Search cache should be cleared (matches search:* pattern)
        expect(SmartCache::has($cacheKey))->toBeFalse();
    });

    it('clears multiple search cache entries with different queries', function () {
        // Arrange - Create multiple search result caches
        $searches = [
            'php laravel',
            'python django',
            'javascript react',
            'database design',
        ];

        $cacheKeys = [];
        foreach ($searches as $query) {
            $cacheKey = 'search:'.md5($query);
            SmartCache::put($cacheKey, ['results' => []], 600);
            $cacheKeys[$cacheKey] = true;
        }

        // Verify all are cached
        foreach ($cacheKeys as $key => $_) {
            expect(SmartCache::has($key))->toBeTrue();
        }

        // Act - Update a product
        $product = Product::factory()->create();

        // Assert - All search caches should be cleared
        foreach ($cacheKeys as $key => $_) {
            expect(SmartCache::has($key))->toBeFalse();
        }
    });

    it('preserves non-search related caches when product changes', function () {
        // Arrange
        $searchCacheKey   = 'search:'.md5('test query');
        $userCacheKey     = 'user.123.profile';
        $settingsCacheKey = 'settings.all';

        SmartCache::put($searchCacheKey, ['results' => []], 600);
        SmartCache::put($userCacheKey, ['name' => 'John', 'email' => 'john@example.com'], 86400);
        SmartCache::put($settingsCacheKey, ['logo' => 'logo.png'], 7200);

        expect(SmartCache::has($searchCacheKey))->toBeTrue();
        expect(SmartCache::has($userCacheKey))->toBeTrue();
        expect(SmartCache::has($settingsCacheKey))->toBeTrue();

        // Act - Update a product
        $product = Product::factory()->create();

        // Assert - Only search cache should be cleared
        expect(SmartCache::has($searchCacheKey))->toBeFalse();
        expect(SmartCache::has($userCacheKey))->toBeTrue();
        expect(SmartCache::has($settingsCacheKey))->toBeTrue();
    });

    it('handles search cache with pagination variations', function () {
        // Arrange - Simulate pagination caches for the same query
        $query     = 'laravel tutorial';
        $cacheKeys = [];

        for ($page = 1; $page <= 3; $page++) {
            for ($perPage = 10; $perPage <= 50; $perPage += 10) {
                $cacheKey = 'search:'.md5($query.$page.$perPage);
                SmartCache::put($cacheKey, ['results' => range(1, $perPage), 'page' => $page], 600);
                $cacheKeys[$cacheKey] = true;
            }
        }

        expect(count($cacheKeys))->toBe(15); // 3 pages * 5 per_page values (10,20,30,40,50)

        // Act - Update product
        $product = Product::factory()->create();

        // Assert - All pagination variations should be cleared
        foreach ($cacheKeys as $key => $_) {
            expect(SmartCache::has($key))->toBeFalse();
        }
    });
});
