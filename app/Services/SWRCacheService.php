<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use SmartCache\Facades\SmartCache;

/**
 * Modern SWR (Stale While Revalidate) Cache Service
 *
 * Implements the SWR caching pattern where stale data is served immediately
 * while fresh data is being fetched in the background.
 *
 * This pattern significantly improves user experience by:
 * - Serving cached data instantly (even if slightly stale)
 * - Fetching fresh data asynchronously
 * - Gracefully degrading if the refresh fails
 *
 * Perfect for non-critical data like:
 * - Homepage content (sliders, partners, testimonials)
 * - Search suggestions
 * - Trending/featured products
 * - Promotional content
 *
 * IMPORTANT: SWR uses SmartCache's built-in swr() method which handles
 * automatic background refresh. We do NOT use Laravel cache tags
 * (they have memory leak issues and are removed from Laravel 10+ docs).
 */
final class SWRCacheService
{
    /**
     * Get or cache data using Stale While Revalidate pattern.
     *
     * @template T
     *
     * @param  string  $key  The cache key to store the data under
     * @param  Closure(): T  $callback  The callback to generate fresh data
     * @param  int  $freshSeconds  How long to serve cached data as "fresh" (before background refresh)
     * @param  int  $staleSeconds  How long to serve cached data as "stale" if refresh fails
     * @return T The data (either fresh from cache, or stale from cache, or newly generated)
     *
     * Example:
     * ```php
     * $sliders = SWRCacheService::remember('homepage.sliders', function() {
     *     return Slider::active()->orderBy('order')->get();
     * }, 300, 900); // 5min fresh, 15min stale
     * ```
     */
    public static function remember(
        string $key,
        Closure $callback,
        int $freshSeconds = 300,
        int $staleSeconds = 900,
    ): mixed {
        // SmartCache's swr() method automatically handles:
        // 1. Returning cached data immediately (within freshSeconds)
        // 2. Queuing background refresh after freshSeconds
        // 3. Returning stale data if refresh fails (up to staleSeconds)
        // 4. Gracefully degrading if neither cache nor callback work
        return SmartCache::swr($key, $callback, $freshSeconds, $staleSeconds);
    }

    /**
     * Homepage content SWR caching
     *
     * Default configuration for homepage content (sliders, partners, stories).
     * These are non-critical and can be slightly stale.
     *
     * - Fresh: 5 minutes (quickly serve cached content)
     * - Stale: 15 minutes (fallback if refresh fails)
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function rememberHomepageContent(string $key, Closure $callback): mixed
    {
        return self::remember($key, $callback, 300, 900);
    }

    /**
     * Search suggestions SWR caching
     *
     * Configuration for search autocomplete suggestions.
     * These are non-critical but benefit from being fresh.
     *
     * - Fresh: 1 hour (users expect consistent suggestions)
     * - Stale: 4 hours (fallback for rare cases)
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function rememberSearchSuggestions(string $key, Closure $callback): mixed
    {
        return self::remember($key, $callback, 3600, 14400);
    }

    /**
     * Trending content SWR caching
     *
     * Configuration for trending products, categories, etc.
     * These need fresher data but benefit from SWR's background refresh.
     *
     * - Fresh: 10 minutes (trending data should update reasonably often)
     * - Stale: 30 minutes (fallback if refresh fails)
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function rememberTrendingContent(string $key, Closure $callback): mixed
    {
        return self::remember($key, $callback, 600, 1800);
    }
}
