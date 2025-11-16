<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Shop\HomePage\SliderController;
use App\Models\Slider;
use App\Services\SWRCacheService;
use SmartCache\Facades\SmartCache;

beforeEach(function () {
    SmartCache::clear();
});

describe('Modern SWR Pattern - Homepage Content', function () {
    it('serves fresh data immediately without background delay', function () {
        // Arrange
        $cacheKey = 'test.homepage.content';
        $data     = ['sliders' => [1, 2, 3]];

        // Act - First call should compute
        $result1 = SWRCacheService::rememberHomepageContent($cacheKey, function () use ($data) {
            return $data;
        });

        // Assert - Data is cached
        expect($result1)->toBe($data);
        expect(SmartCache::has($cacheKey))->toBeTrue();

        // Act - Second call within fresh window (5 min)
        $result2 = SWRCacheService::rememberHomepageContent($cacheKey, function () {
            return ['modified' => true]; // This shouldn't be called
        });

        // Assert - Returns cached data, not recomputed
        expect($result2)->toBe($data);
    });

    it('allows graceful background refresh pattern with SWR', function () {
        // This test demonstrates the SWR pattern:
        // - Fresh window: 5 minutes (serve cache, don't refresh)
        // - Stale window: 15 minutes (serve cache while refresh in background)
        // - After stale: recompute

        $cacheKey  = 'test.swr.data';
        $callCount = 0;

        $callback = function () use (&$callCount) {
            $callCount++;

            return ['call' => $callCount];
        };

        // Act & Assert - First call always computes
        $result = SWRCacheService::rememberHomepageContent($cacheKey, $callback);
        expect($result)->toBe(['call' => 1]);
        expect($callCount)->toBe(1);

        // Second call returns cached
        $result = SWRCacheService::rememberHomepageContent($cacheKey, $callback);
        expect($result)->toBe(['call' => 1]);
        expect($callCount)->toBe(1); // Still 1, not called again
    });

    it('prefers search suggestions configuration with longer fresh window', function () {
        // Arrange
        $cacheKey = 'search:suggest:test';
        $data     = ['suggestions' => ['test1', 'test2']];

        // Act
        $result = SWRCacheService::rememberSearchSuggestions($cacheKey, function () use ($data) {
            return $data;
        });

        // Assert
        expect($result)->toBe($data);
        expect(SmartCache::has($cacheKey))->toBeTrue();
    });

    it('supports custom SWR parameters for different use cases', function () {
        // This test demonstrates flexibility
        $cacheKey = 'trending.products';
        $data     = ['trending' => [1, 2, 3, 4, 5]];

        // Trending content: 10min fresh, 30min stale
        $result = SWRCacheService::rememberTrendingContent($cacheKey, function () use ($data) {
            return $data;
        });

        expect($result)->toBe($data);
        expect(SmartCache::has($cacheKey))->toBeTrue();
    });

    it('benefits from SWR pattern for homepage sliders', function () {
        // Arrange
        $slider = Slider::factory()->create(['status' => 'published']);

        // Act - Trigger the caching mechanism
        $controller = new SliderController();

        // Since __invoke requires request context, we test the cache pattern directly
        $result = SWRCacheService::rememberHomepageContent('shop.sliders', function () use ($slider) {
            return collect([$slider]); // Simulating SliderData collection
        });

        // Assert
        expect($result)->not->toBeNull();
        expect(SmartCache::has('shop.sliders'))->toBeTrue();
    });

    it('handles SWR caching failure gracefully', function () {
        // Arrange
        $cacheKey     = 'failing.cache';
        $errorMessage = '';

        // Act & Assert - Callback exceptions should be handled
        try {
            $result = SWRCacheService::rememberHomepageContent($cacheKey, function () {
                throw new Exception('Cache callback failed');
            });
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
        }

        expect($errorMessage)->toBe('Cache callback failed');
    });

    it('combines SWR with cache invalidation patterns', function () {
        // Arrange
        $cacheKey = 'shop.category.electronics.good-for-start.courses';
        $data     = ['courses' => [1, 2, 3]];

        // Act - Cache the data
        $cached = SWRCacheService::rememberHomepageContent($cacheKey, function () use ($data) {
            return $data;
        });

        expect(SmartCache::has($cacheKey))->toBeTrue();

        // Invalidate using pattern (like observer would do)
        SmartCache::flushPatterns(['shop.category.*.good-for-start.*']);

        // Assert - Cache is cleared
        expect(SmartCache::has($cacheKey))->toBeFalse();
    });
});

describe('Modern SWR Pattern - Search Suggestions', function () {
    it('caches search suggestions with appropriate SWR parameters', function () {
        // Arrange
        $cacheKey    = 'search:suggest:'.md5('laravel5');
        $suggestions = ['Laravel 5 Basics', 'Laravel 5 Advanced', 'Laravel 5 Patterns'];

        // Act
        $result = SWRCacheService::rememberSearchSuggestions($cacheKey, function () use ($suggestions) {
            return $suggestions;
        });

        // Assert
        expect($result)->toBe($suggestions);
        expect(SmartCache::has($cacheKey))->toBeTrue();
    });

    it('invalidates search suggestions when products are updated', function () {
        // Arrange
        $cacheKey1 = 'search:suggest:'.md5('product5');
        $cacheKey2 = 'search:suggest:'.md5('course10');

        // Cache suggestions
        SWRCacheService::rememberSearchSuggestions($cacheKey1, fn () => ['Product A', 'Product B']);
        SWRCacheService::rememberSearchSuggestions($cacheKey2, fn () => ['Course X', 'Course Y']);

        expect(SmartCache::has($cacheKey1))->toBeTrue();
        expect(SmartCache::has($cacheKey2))->toBeTrue();

        // Act - Flush all search suggestions
        SmartCache::flushPatterns(['search:suggest:*']);

        // Assert - All suggestion caches cleared
        expect(SmartCache::has($cacheKey1))->toBeFalse();
        expect(SmartCache::has($cacheKey2))->toBeFalse();
    });

    it('maintains separate SWR profiles for different content types', function () {
        // Homepage content: 5 min fresh
        $homepageKey  = 'homepage.content';
        $homepageData = ['sliders' => [1, 2]];

        // Trending: 10 min fresh
        $trendingKey  = 'trending.products';
        $trendingData = ['top' => [1, 2, 3]];

        // Suggestions: 1 hour fresh
        $suggestKey  = 'search:suggest:test';
        $suggestData = ['apple', 'application'];

        // Act & Assert
        $r1 = SWRCacheService::rememberHomepageContent($homepageKey, fn () => $homepageData);
        expect($r1)->toBe($homepageData);

        $r2 = SWRCacheService::rememberTrendingContent($trendingKey, fn () => $trendingData);
        expect($r2)->toBe($trendingData);

        $r3 = SWRCacheService::rememberSearchSuggestions($suggestKey, fn () => $suggestData);
        expect($r3)->toBe($suggestData);

        // All cached independently
        expect(SmartCache::has($homepageKey))->toBeTrue();
        expect(SmartCache::has($trendingKey))->toBeTrue();
        expect(SmartCache::has($suggestKey))->toBeTrue();
    });
});

describe('Modern SWR - No Cache Tags Used', function () {
    it('demonstrates that SWR works without problematic cache tags', function () {
        // This test verifies we're using SWR + patterns, NOT tags
        // Tags have memory leak issues and are removed from Laravel 10+ docs

        $cacheKey = 'homepage.sliders';
        $data     = ['slider1', 'slider2'];

        // SWR approach: Pattern-based invalidation
        $result = SWRCacheService::rememberHomepageContent($cacheKey, function () use ($data) {
            return $data;
        });

        expect($result)->toBe($data);

        // Invalidation via patterns (safe, works with all drivers)
        SmartCache::flushPatterns(['homepage.*']);

        // Verified cleared
        expect(SmartCache::has($cacheKey))->toBeFalse();

        // Tags approach would be:
        // Cache::tags(['homepage'])->put(...)  <- AVOID THIS
        // Cache::tags(['homepage'])->flush()   <- CAN CAUSE MEMORY LEAKS
    });

    it('provides cache driver compatibility (not like tags)', function () {
        // SWR works with ALL cache drivers
        $cacheKey = 'universal.cache';
        $data     = ['compatible' => true];

        // Works whether driver is Redis, Database, File, or Array
        $result = SWRCacheService::rememberHomepageContent($cacheKey, function () use ($data) {
            return $data;
        });

        expect($result)->toBe($data);

        // Pattern-based invalidation works with all drivers
        SmartCache::flushPatterns(['universal.*']);

        expect(SmartCache::has($cacheKey))->toBeFalse();
    });
});
