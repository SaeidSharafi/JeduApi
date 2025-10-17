<?php

declare(strict_types=1);

use App\Enums\System\CacheKeysEnum;
use App\Models\Category;
use App\Models\Product;
use SmartCache\Facades\SmartCache;

beforeEach(function () {
    SmartCache::clear();
});

describe('GoodForStartCoursesController Cache Invalidation', function () {
    it('clears good-for-start cache when product is updated', function () {
        // Arrange - Simulate GoodForStartCoursesController caching with query parameters
        $cacheKey1 = CacheKeysEnum::GoodForStart->key(['slug' => 'programming']).'-5';
        $cacheKey2 = CacheKeysEnum::GoodForStart->key(['slug' => 'programming']).'-10';
        $cacheKey3 = CacheKeysEnum::GoodForStart->key(['slug' => 'data-science']).'-5';

        SmartCache::put($cacheKey1, ['courses' => [1, 2, 3]], 1800);
        SmartCache::put($cacheKey2, ['courses' => [1, 2]], 1800);
        SmartCache::put($cacheKey3, ['courses' => [4, 5]], 1800);

        expect(SmartCache::has($cacheKey1))->toBeTrue();
        expect(SmartCache::has($cacheKey2))->toBeTrue();
        expect(SmartCache::has($cacheKey3))->toBeTrue();

        // Act - Update a product
        $product = Product::factory()->create();

        // Assert - All good-for-start caches matching the pattern should be cleared
        expect(SmartCache::has($cacheKey1))->toBeFalse();
        expect(SmartCache::has($cacheKey2))->toBeFalse();
        expect(SmartCache::has($cacheKey3))->toBeFalse();
    });

    it('clears category-specific good-for-start cache when category is updated', function () {
        // Arrange
        $category = Category::factory()->create(['slug' => 'advanced-web']);
        $cacheKey = CacheKeysEnum::GoodForStart->key(['slug' => 'advanced-web']).'-10';

        SmartCache::put($cacheKey, ['courses' => [1, 2]], 1800);
        expect(SmartCache::has($cacheKey))->toBeTrue();

        // Act - Update the category
        $category->update(['name' => 'Advanced Web Development']);

        // Assert - Category-specific cache should be cleared
        expect(SmartCache::has($cacheKey))->toBeFalse();
    });

    it('handles multiple good-for-start caches with different limit parameters', function () {
        // Arrange - Simulate multiple requests with different limit query parameters
        $slug   = 'beginner';
        $caches = [];
        for ($limit = 5; $limit <= 25; $limit += 5) {
            $key = CacheKeysEnum::GoodForStart->key(['slug' => $slug]).'-'.$limit;
            SmartCache::put($key, ['courses' => range(1, $limit / 5)], 1800);
            $caches[$key] = true;
        }

        // Verify all are cached
        foreach ($caches as $key => $_) {
            expect(SmartCache::has($key))->toBeTrue();
        }

        // Act - Update a product
        $product = Product::factory()->create();

        // Assert - All caches should be cleared
        foreach ($caches as $key => $_) {
            expect(SmartCache::has($key))->toBeFalse();
        }
    });
});
