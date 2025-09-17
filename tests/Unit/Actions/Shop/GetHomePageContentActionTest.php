<?php

declare(strict_types=1);

use App\Actions\Shop\GetHomePageContentAction;
use App\Enums\HomePageBlockTypeEnum;
use App\Models\HomePageBlock;
use App\Models\Category;
use App\Models\Product;

beforeEach(function () {
    \Illuminate\Support\Facades\Storage::fake('public');
});

describe('GetHomePageContentAction', function () {
    it('can handle empty blocks', function () {
        $action = new GetHomePageContentAction();
        $result = $action->handle();

        expect($result)->toBeInstanceOf(\App\Data\Shop\HomePageContentData::class)
            ->and($result->hero)->toBeArray()
            ->and($result->main_content)->toBeArray()
            ->and(count($result->hero))->toBe(0)
            ->and(count($result->main_content))->toBe(0);
    });

    it('can handle curated list blocks', function () {
        // Create test data
        $categories = Category::factory()->count(3)->create();
        $products = Product::factory()->count(3)->create();

        // Create blocks
        HomePageBlock::factory()->curatedList(
            $categories->pluck('id')->toArray(),
            HomePageBlockTypeEnum::MAIN_CATEGORIES
        )->create([
            'title' => 'Main Categories',
            'location' => 'main_content',
            'order' => 1,
            'is_active' => true,
        ]);

        HomePageBlock::factory()->curatedList(
            $products->pluck('id')->toArray(),
            HomePageBlockTypeEnum::CURATED_LIST
        )->create([
            'title' => 'Featured Products',
            'location' => 'main_content',
            'order' => 2,
            'is_active' => true,
        ]);

        $action = new GetHomePageContentAction();
        $result = $action->handle();

        expect($result)->toBeInstanceOf(\App\Data\Shop\HomePageContentData::class)
            ->and(count($result->main_content))->toBe(2)
            ->and($result->main_content[0]['title'])->toBe('Main Categories')
            ->and($result->main_content[0]['type'])->toBe('MAIN_CATEGORIES')
            ->and($result->main_content[1]['title'])->toBe('Featured Products')
            ->and($result->main_content[1]['type'])->toBe('CURATED_LIST')
            ->and(count($result->main_content[0]['content']['items']))->toBe(3)
            ->and(count($result->main_content[1]['content']['items']))->toBe(3);
    });

    it('can handle dynamic list blocks', function () {
        // Create test data
        Product::factory()->count(5)->create();

        // Create dynamic list block
        HomePageBlock::factory()->dynamicList(
            'all_products',
            'created_at:desc',
            3
        )->create([
            'title' => 'Latest Products',
            'location' => 'main_content',
            'order' => 1,
            'is_active' => true,
        ]);

        $action = new GetHomePageContentAction();
        $result = $action->handle();

        expect($result)->toBeInstanceOf(\App\Data\Shop\HomePageContentData::class)
            ->and(count($result->main_content))->toBe(1)
            ->and($result->main_content[0]['title'])->toBe('Latest Products')
            ->and($result->main_content[0]['type'])->toBe('DYNAMIC_LIST')
            ->and(count($result->main_content[0]['content']['items']))->toBe(3);
    });

    it('filters inactive blocks', function () {
        // Create active and inactive blocks
        HomePageBlock::factory()->banner()->create([
            'title' => 'Active Block',
            'location' => 'main_content',
            'is_active' => true,
        ]);

        HomePageBlock::factory()->banner()->create([
            'title' => 'Inactive Block',
            'location' => 'main_content',
            'is_active' => false,
        ]);

        $action = new GetHomePageContentAction();
        $result = $action->handle();

        expect(count($result->main_content))->toBe(1)
            ->and($result->main_content[0]['title'])->toBe('Active Block');
    });

    it('orders blocks correctly', function () {
        HomePageBlock::factory()->banner()->create([
            'title' => 'Main Block 2',
            'location' => 'main_content',
            'order' => 2,
            'is_active' => true,
        ]);

        HomePageBlock::factory()->banner()->create([
            'title' => 'Hero Block',
            'location' => 'hero',
            'order' => 1,
            'is_active' => true,
        ]);

        HomePageBlock::factory()->banner()->create([
            'title' => 'Main Block 1',
            'location' => 'main_content',
            'order' => 1,
            'is_active' => true,
        ]);

        $action = new GetHomePageContentAction();
        $result = $action->handle();

        expect(count($result->hero))->toBe(1)
            ->and(count($result->main_content))->toBe(2)
            ->and($result->hero[0]['title'])->toBe('Hero Block')
            ->and($result->main_content[0]['title'])->toBe('Main Block 1')
            ->and($result->main_content[1]['title'])->toBe('Main Block 2');
    });
});
