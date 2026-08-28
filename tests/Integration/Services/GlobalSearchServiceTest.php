<?php

declare(strict_types=1);

use App\Data\Shop\Search\SearchData;
use App\Models\Blog\BlogPost;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Services\GlobalSearchService;

describe('Searchable Arrays Schema Integrity', function (): void {
    it('prepares the correct searchable data array for Product', function (): void {
        $product = Product::factory()
            ->withCategory(1)
            ->withDeliveryOptions(1)
            ->create([
                'name'       => 'Advanced PHP Design Patterns',
                'is_visible' => true,
            ]);

        $searchableArray = $product->toSearchableArray();

        // 1. Assert required Typesense field keys exist
        expect($searchableArray)->toHaveKeys([
            'id',
            'name',
            'short_name',
            'short_description',
            'slug',
            'price',
            'category_ids',
            'category_slugs',
            'status',
            'is_visible',
        ]);

        // 2. Assert data formats are correct
        expect($searchableArray['name'])->toBe('Advanced PHP Design Patterns')
            ->and($searchableArray['is_visible'])->toBeTrue()
            ->and($searchableArray['price'])->toBeInt()
            ->and($searchableArray['category_slugs'])->toBeArray();
    });

    it('prepares the correct searchable data array for BlogPost', function (): void {
        $post = BlogPost::factory()->create([
            'title'  => 'The Future of Laravel',
            'status' => 'published',
        ]);

        $searchableArray = $post->toSearchableArray();

        expect($searchableArray)->toHaveKeys([
            'id',
            'title',
            'slug',
            'body',
            'excerpt',
            'status',
        ])
            ->and($searchableArray['title'])->toBe('The Future of Laravel');
    });
});

describe('Database Search Fallback & Filter Verification', function (): void {

    beforeEach(function (): void {
        // Force the database driver for these deterministic tests
        Config::set('scout.driver', 'database');
    });

    it('filters query by price ranges accurately', function (): void {
        // Create an expensive course
        $expensive = Product::factory()->withDeliveryOptions(1)->create(['name' => 'React Course Pro']);

        ProductPrice::query()->create([
            'product_id'              => $expensive->id,
            'min_price'               => 500_000,
            'max_price'               => 500_000,
            'min_original_price'      => 500_000,
            'max_original_price'      => 500_000,
            'has_discount'            => false,
            'has_featured_price'      => false,
            'has_prepayment'          => false,
            'discount_percentage'     => 0,
            'highest_discount_amount' => 0,
        ]);

        // Create a cheap course
        $cheap = Product::factory()->withDeliveryOptions(1)->create(['name' => 'React Basics']);

        ProductPrice::query()->create([
            'product_id'              => $cheap->id,
            'min_price'               => 100_000,
            'max_price'               => 100_000,
            'min_original_price'      => 100_000,
            'max_original_price'      => 100_000,
            'has_discount'            => false,
            'has_featured_price'      => false,
            'has_prepayment'          => false,
            'discount_percentage'     => 0,
            'highest_discount_amount' => 0,
        ]);

        $service = app(GlobalSearchService::class);

        $results = $service->search(SearchData::from([
            'q'      => 'React',
            'filter' => [
                'min_price' => 200_000,
                'max_price' => 600_000,
            ],
        ]));

        // Assert only the expensive one is returned
        expect($results->total())->toBe(1)
            ->and($results->items()[0]->name)->toBe('React Course Pro');
    });

    it('filters by availability dates correctly', function (): void {
        $now = now();

        // 1. Past Course (Available in the past)
        $past = Product::factory()->withDeliveryOptions(realData: [
            [
                'available_from' => $now->clone()->subDays(10),
                'available_to'   => $now->clone()->subDays(2),
            ],
        ])->create(['name' => 'Legacy Course']);

        // 2. Active Course (Available now)
        $active = Product::factory()->withDeliveryOptions(realData: [
            [
                'available_from' => $now->clone()->subDays(1),
                'available_to'   => $now->clone()->addDays(5),
            ],
        ])->create(['name' => 'Modern Course']);

        $service = app(GlobalSearchService::class);

        // Fetch "available now" items (default behavior) using 'Course' to match both titles
        $results = $service->search(SearchData::from([
            'q' => 'Course',
        ]));

        // Check legacy is excluded and active is included
        $names = collect($results->items())->pluck('name');
        expect($names)->toContain('Modern Course')
            ->and($names)->not->toContain('Legacy Course');
    });
});
