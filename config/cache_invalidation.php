<?php

use App\Models\ProductDeliveryOptionDiscountPrice;
use App\Models\Blog\BlogPost;
use App\Models\Category;
use App\Models\Course;
use App\Models\DiscountPromotion;
use App\Models\HomePageBlock;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Review;
use App\Models\Seminar;
use \App\Enums\CacheKeysEnum;

return [
    /**
     * This map connects models to the cache keys they should invalidate on change.
     * When any model on the left is updated, created, or deleted,
     * every cache key in the array on the right will be cleared.
     */
    'map' => [
        HomePageBlock::class                      => [
            CacheKeysEnum::HomePageContent,
        ],
        Product::class                            => [
            CacheKeysEnum::HomePageContent,
        ],
        ProductDeliveryOption::class              => [
            CacheKeysEnum::HomePageContent,
        ],
        ProductDeliveryOptionDiscountPrice::class => [
            CacheKeysEnum::HomePageContent,
        ],
        Course::class                             => [
            CacheKeysEnum::HomePageContent,
        ],
        Seminar::class                            => [
            CacheKeysEnum::HomePageContent,
        ],
        Category::class                           => [
            CacheKeysEnum::HomePageContent,
        ],
        DiscountPromotion::class                  => [
            CacheKeysEnum::HomePageContent,
        ],
        Review::class                             => [
            CacheKeysEnum::HomePageContent,
        ],
        BlogPost::class                           => [
            CacheKeysEnum::HomePageContent,
        ],

        // You can add other cache keys here too!
        // For example, if you have a separate cache for just categories:
        // Category::class => [
        //     CacheKeysEnum::HomePageContent,
        //     'categories:all_for_menu',
        // ],
    ],
];
