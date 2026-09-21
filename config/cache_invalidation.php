<?php

declare(strict_types=1);

use App\Models\Blog\BlogPost;
use App\Models\Category;
use App\Models\Course;
use App\Models\DiscountPromotion;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\ProductDeliveryOptionDiscountPrice;
use App\Models\Review;
use App\Models\Seminar;

return [
    /**
     * This map connects models to the cache keys they should invalidate on change.
     * When any model on the left is updated, created, or deleted,
     * every cache key in the array on the right will be cleared.
     *
     * Supported formats:
     * 1. String literals: Direct cache keys (e.g., 'my_cache_key')
     * 2. Pattern-based: ['type' => 'pattern', 'value' => 'shop.category.*.good-for-start.*']
     * 3. Tags (Redis): ['type' => 'tag', 'value' => 'products'] or ['type' => 'tag', 'value' => ['products', 'pricing']]
     *
     * Examples:
     * - 'exact_cache_key'                              // Direct string key
     * - ['type' => 'pattern', 'value' => 'search:*']  // Wildcard patterns (Database/File drivers)
     */
    'map' => [
        Product::class => [
            // Clear all good-for-start course caches when a product changes
            ['type' => 'pattern', 'value' => 'shop.category.*.good-for-start.courses*'],
            // Clear search results when product data changes
            ['type' => 'pattern', 'value' => 'search:*'],
            // Clear search suggestions (SWR caches)
            ['type' => 'pattern', 'value' => 'search:suggest:*'],
        ],
        ProductDeliveryOption::class => [
            ['type' => 'pattern', 'value' => 'shop.category.*.good-for-start.courses*'],
            ['type' => 'pattern', 'value' => 'search:suggest:*'],
        ],
        ProductDeliveryOptionDiscountPrice::class => [
            ['type' => 'pattern', 'value' => 'search:*'],
            ['type' => 'pattern', 'value' => 'search:suggest:*'],
        ],
        Course::class => [
            ['type' => 'pattern', 'value' => 'shop.category.*.good-for-start.courses*'],
            ['type' => 'pattern', 'value' => 'search:*'],
            ['type' => 'pattern', 'value' => 'search:suggest:*'],
        ],
        Seminar::class => [
            ['type' => 'pattern', 'value' => 'search:*'],
            ['type' => 'pattern', 'value' => 'search:suggest:*'],
        ],
        Category::class => [
            // Clear category-specific good-for-start caches
            ['type' => 'pattern', 'value' => 'shop.category.*.good-for-start.courses*'],
            ['type' => 'pattern', 'value' => 'search:suggest:*'],
        ],
        DiscountPromotion::class => [
            ['type' => 'pattern', 'value' => 'search:*'],
            ['type' => 'pattern', 'value' => 'search:suggest:*'],
        ],
        Review::class => [
            ['type' => 'pattern', 'value' => 'search:*'],
            ['type' => 'pattern', 'value' => 'search:suggest:*'],
        ],
        BlogPost::class => [
            ['type' => 'pattern', 'value' => 'search:*'],
            ['type' => 'pattern', 'value' => 'search:suggest:*'],
        ],
        App\Models\Categorizable::class => [
            ['type' => 'pattern', 'value' => 'shop.category.*.good-for-start.courses*'],
        ],
    ],
];
