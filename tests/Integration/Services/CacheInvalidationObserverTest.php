<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\Slider;
use SmartCache\Facades\SmartCache;

beforeEach(function (): void {
    SmartCache::clear();
});

describe('InvalidationObserver - Cache Invalidation', function (): void {
    it('clears mapped pattern caches when product is updated', function (): void {
        // Arrange
        $suggestionKey = 'search:suggest:'.md5('django');
        SmartCache::put($suggestionKey, ['data' => 'test'], 3600);
        expect(SmartCache::has($suggestionKey))->toBeTrue();

        // Act
        $product = Product::factory()->create();

        // Assert
        expect(SmartCache::has($suggestionKey))->toBeFalse();
    });

    it('clears mapped pattern caches when category is deleted', function (): void {
        // Arrange
        $goodForStartKey = 'shop.category.programming.good-for-start.courses';
        SmartCache::put($goodForStartKey, ['courses' => [1]], 3600);
        expect(SmartCache::has($goodForStartKey))->toBeTrue();

        // Act
        $category = Category::factory()->create(['slug' => 'programming']);
        $category->delete();

        // Assert
        expect(SmartCache::has($goodForStartKey))->toBeFalse();
    });

    it('leaves caches of models that moved to explicit invalidation untouched', function (): void {
        // Arrange - Slider is no longer in the observer map; its action clears the gateway key.
        SmartCache::put('shop.homepage.sliders', [['id' => 1]], 7200);
        expect(SmartCache::has('shop.homepage.sliders'))->toBeTrue();

        // Act
        $slider = Slider::factory()->create();
        $slider->update(['title' => 'Updated Slider']);

        // Assert
        expect(SmartCache::has('shop.homepage.sliders'))->toBeTrue();
    });

    it('preserves unrelated cache keys during invalidation', function (): void {
        // Arrange
        $suggestionKey = 'search:suggest:'.md5('laravel');
        SmartCache::put($suggestionKey, ['data' => 'test'], 3600);
        SmartCache::put('user.123.profile', ['name' => 'John'], 86400);

        expect(SmartCache::has($suggestionKey))->toBeTrue();
        expect(SmartCache::has('user.123.profile'))->toBeTrue();

        // Act - Update product (only affects product-related patterns)
        $product = Product::factory()->create();

        // Assert - Only product-related keys are cleared
        expect(SmartCache::has($suggestionKey))->toBeFalse();
        expect(SmartCache::has('user.123.profile'))->toBeTrue();
    });

    it('handles multiple cache invalidation patterns for a single model', function (): void {
        // Arrange - Store multiple types of caches
        $searchKey     = 'search:'.md5('laravel');
        $suggestionKey = 'search:suggest:'.md5('laravel');
        $goodForStart  = 'shop.category.programming.good-for-start.courses';
        SmartCache::put($searchKey, ['data' => 'search'], 3600);
        SmartCache::put($suggestionKey, ['data' => 'suggest'], 3600);
        SmartCache::put($goodForStart, ['courses' => [1]], 3600);

        expect(SmartCache::has($searchKey))->toBeTrue();
        expect(SmartCache::has($suggestionKey))->toBeTrue();
        expect(SmartCache::has($goodForStart))->toBeTrue();

        // Act
        $product = Product::factory()->create();

        // Assert - Every configured pattern is cleared
        expect(SmartCache::has($searchKey))->toBeFalse();
        expect(SmartCache::has($suggestionKey))->toBeFalse();
        expect(SmartCache::has($goodForStart))->toBeFalse();
    });
});
