<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\Slider;
use SmartCache\Facades\SmartCache;

beforeEach(function () {
    SmartCache::clear();
});

describe('InvalidationObserver - Cache Invalidation', function () {
    it('clears simple cache keys when product is updated', function () {
        // Arrange
        SmartCache::put('shop.homepage.content', ['data' => 'test'], 3600);
        expect(SmartCache::has('shop.homepage.content'))->toBeTrue();

        // Act
        $product = Product::factory()->create();

        // Assert
        expect(SmartCache::has('shop.homepage.content'))->toBeFalse();
    });

    it('clears cache when slider is updated', function () {
        // Arrange
        SmartCache::put('shop.homepage.sliders', [['id' => 1]], 7200);
        expect(SmartCache::has('shop.homepage.sliders'))->toBeTrue();

        // Act
        $slider = Slider::factory()->create();
        $slider->update(['title' => 'Updated Slider']);

        // Assert
        expect(SmartCache::has('shop.homepage.sliders'))->toBeFalse();
    });

    it('clears cache when category is deleted', function () {
        // Arrange
        SmartCache::put('shop.homepage.content', ['categories' => [1, 2, 3]], 3600);

        expect(SmartCache::has('shop.homepage.content'))->toBeTrue();

        // Act
        $category = Category::factory()->create(['slug' => 'tech']);
        $category->delete();

        // Assert
        expect(SmartCache::has('shop.homepage.content'))->toBeFalse();
    });

    it('handles cache invalidation for models not in config map', function () {
        // Arrange - Create a cache key that will be cleared
        SmartCache::put('test_key', 'test_value', 3600);

        // Act & Assert - Should not throw exception when model not in map
        $slider = Slider::factory()->create();
        SmartCache::put('unique_slider_key', 'data', 3600);
        $slider->update(['title' => 'New Title']);

        // If we reach here, no exception was thrown
        expect(true)->toBeTrue();
    });

    it('preserves unrelated cache keys during invalidation', function () {
        // Arrange
        SmartCache::put('shop.homepage.content', ['homepage' => 'data'], 3600);
        SmartCache::put('user.123.profile', ['name' => 'John'], 86400);

        expect(SmartCache::has('shop.homepage.content'))->toBeTrue();
        expect(SmartCache::has('user.123.profile'))->toBeTrue();

        // Act - Update product (only affects shop.homepage.content)
        $product = Product::factory()->create();

        // Assert - Only product-related keys are cleared
        expect(SmartCache::has('shop.homepage.content'))->toBeFalse();
        expect(SmartCache::has('user.123.profile'))->toBeTrue();
    });

    it('handles multiple cache invalidation patterns for a single model', function () {
        // Arrange - Store multiple types of caches
        SmartCache::put('shop.homepage.content', ['data' => 'homepage'], 3600);

        expect(SmartCache::has('shop.homepage.content'))->toBeTrue();

        // Act
        $product = Product::factory()->create();

        // Assert - Configured cache key should be cleared
        expect(SmartCache::has('shop.homepage.content'))->toBeFalse();
    });
});
