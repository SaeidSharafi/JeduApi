<?php

declare(strict_types=1);

use App\Enums\System\CacheKeysEnum;
use App\Models\Blog\BlogPost;
use App\Models\Category;
use App\Models\Course;
use App\Models\DiscountPromotion;
use App\Models\HomePageBlock;
use App\Models\Partner;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\ProductDeliveryOptionDiscountPrice;
use App\Models\Review;
use App\Models\Seminar;
use App\Models\Slider;
use App\Models\StudentStory;

return [
    /**
     * This map connects models to the cache keys they should invalidate on change.
     * When any model on the left is updated, created, or deleted,
     * every cache key in the array on the right will be cleared.
     *
     * Supported formats:
     * 1. CacheKeysEnum instances: Simple cache key enums with optional parameters
     * 2. String literals: Direct cache keys (e.g., 'my_cache_key')
     * 3. Pattern-based: ['type' => 'pattern', 'value' => 'shop.category.*.good-for-start.*']
     * 4. Tags (Redis): ['type' => 'tag', 'value' => 'products'] or ['type' => 'tag', 'value' => ['products', 'pricing']]
     *
     * Examples:
     * - CacheKeysEnum::HomePageContent  // Simple enum
     * - 'exact_cache_key'              // Direct string key
     * - ['type' => 'pattern', 'value' => 'search:*']  // Wildcard patterns (Database/File drivers)
     */
    'map' => [
        HomePageBlock::class => [
            CacheKeysEnum::HomePageContent,
        ],
        Product::class => [
            CacheKeysEnum::HomePageContent,
            // Clear all good-for-start course caches when a product changes
            ['type' => 'pattern', 'value' => 'shop.category.*.good-for-start.courses*'],
            // Clear search results when product data changes
            ['type' => 'pattern', 'value' => 'search:*'],
            // Clear search suggestions (SWR caches)
            ['type' => 'pattern', 'value' => 'search:suggest:*'],
        ],
        ProductDeliveryOption::class => [
            CacheKeysEnum::HomePageContent,
            ['type' => 'pattern', 'value' => 'shop.category.*.good-for-start.courses*'],
            ['type' => 'pattern', 'value' => 'search:suggest:*'],
        ],
        ProductDeliveryOptionDiscountPrice::class => [
            CacheKeysEnum::HomePageContent,
            ['type' => 'pattern', 'value' => 'search:*'],
            ['type' => 'pattern', 'value' => 'search:suggest:*'],
        ],
        Course::class => [
            CacheKeysEnum::HomePageContent,
            ['type' => 'pattern', 'value' => 'shop.category.*.good-for-start.courses*'],
            ['type' => 'pattern', 'value' => 'search:*'],
            ['type' => 'pattern', 'value' => 'search:suggest:*'],
        ],
        Seminar::class => [
            CacheKeysEnum::HomePageContent,
            ['type' => 'pattern', 'value' => 'search:*'],
            ['type' => 'pattern', 'value' => 'search:suggest:*'],
        ],
        Category::class => [
            CacheKeysEnum::HomePageContent,
            // Clear category-specific good-for-start caches
            ['type' => 'pattern', 'value' => 'shop.category.*.good-for-start.courses*'],
            ['type' => 'pattern', 'value' => 'search:suggest:*'],
        ],
        DiscountPromotion::class => [
            CacheKeysEnum::HomePageContent,
            ['type' => 'pattern', 'value' => 'search:*'],
            ['type' => 'pattern', 'value' => 'search:suggest:*'],
        ],
        Review::class => [
            CacheKeysEnum::HomePageContent,
            ['type' => 'pattern', 'value' => 'search:*'],
            ['type' => 'pattern', 'value' => 'search:suggest:*'],
        ],
        BlogPost::class => [
            CacheKeysEnum::HomePageContent,
            ['type' => 'pattern', 'value' => 'search:*'],
            ['type' => 'pattern', 'value' => 'search:suggest:*'],
        ],
        StudentStory::class => [
            ['type' => 'pattern', 'value' => CacheKeysEnum::StudentStory->value.'*'],
        ],
        Slider::class => [
            CacheKeysEnum::Slider,
        ],
        Partner::class => [
            CacheKeysEnum::PartnersInHome,
            CacheKeysEnum::PartnersInCourse,
            CacheKeysEnum::Partners,
        ],
        App\Models\Setting::class => [
            CacheKeysEnum::Settings,
        ],
        App\Models\Categorizable::class => [
            CacheKeysEnum::HomePageContent,
            ['type' => 'pattern', 'value' => 'shop.category.*.good-for-start.courses*'],
        ],
    ],
];
