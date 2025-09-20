<?php

declare(strict_types=1);

use App\Enums\DynamicListEntityTypeEnum;
use App\Enums\DynamicListSortByEnum;
use App\Enums\HomePageBlockTypeEnum;
use App\Models\Blog\BlogPost;
use App\Models\Category;
use App\Models\HomePageBlock;
use App\Models\Product;

beforeEach(function (): void {
    Storage::fake('public');
    $this->media = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('image1.jpg'))
        ->toDisk('public')
        ->upload();
    $this->media2 = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('image2.jpg'))
        ->toDisk('public')
        ->upload();
    $this->file1 = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'))
        ->toDisk('public')
        ->upload();
});

describe('HomePageContentController', function (): void {
    it('can retrieve home page content with empty blocks', function (): void {
        $response = $this->getJson(route('api.v1.shop.home-page-content'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'hero',
                    'main_content',
                ]
            ]);

        $responseData = $response->json('data');
        expect($responseData['hero'])->toBeArray()
            ->and($responseData['main_content'])->toBeArray();
    });

    it('can retrieve home page content with curated list blocks', function (): void {
        // Create some test categories and products
        $categories = Category::factory()->count(3)->create();
        $products = Product::factory()->withDeliveryOptions()
            ->count(3)->create();

        // Create a MAIN_CATEGORIES block
        $categoryBlock = HomePageBlock::factory()->curatedList(
            $categories->pluck('id')->toArray(),
            HomePageBlockTypeEnum::MAIN_CATEGORIES
        )->create([
            'title' => 'Main Categories',
            'location' => 'main_content',
            'order' => 1,
            'is_active' => true,
        ]);

        // Create a CURATED_LIST block
        $productBlock = HomePageBlock::factory()->curatedList(
            $products->pluck('id')->toArray(),
            HomePageBlockTypeEnum::CURATED_LIST
        )->create([
            'title' => 'Featured Products',
            'location' => 'main_content',
            'order' => 2,
            'is_active' => true,
        ]);

        $response = $this->getJson(route('api.v1.shop.home-page-content'));

        $response->assertStatus(200);
        $responseData = $response->json('data');

        expect(count($responseData['main_content']))->toBe(2)
            ->and($responseData['main_content'][0]['title'])->toBe('Main Categories')
            ->and($responseData['main_content'][0]['type'])->toBe('MAIN_CATEGORIES')
            ->and($responseData['main_content'][1]['title'])->toBe('Featured Products')
            ->and($responseData['main_content'][1]['type'])->toBe('CURATED_LIST')
            ->and(count($responseData['main_content'][0]['content']['items']))->toBe(3)
            ->and(count($responseData['main_content'][1]['content']['items']))->toBe(3);
    });

    it('can retrieve home page content with dynamic list blocks', function (): void {
        // Create some test products
        Product::factory()
            ->withDeliveryOptions()
            ->count(5)->create();

        // Create a DYNAMIC_LIST block
        $dynamicBlock = HomePageBlock::factory()->dynamicList(
            DynamicListEntityTypeEnum::ALL_PRODUCTS,
            DynamicListSortByEnum::CREATED_AT_DESC,
            3
        )->create([
            'title' => 'Latest Products',
            'location' => 'main_content',
            'order' => 1,
            'is_active' => true,
        ]);

        $response = $this->getJson(route('api.v1.shop.home-page-content'));

        $response->assertStatus(200);
        $responseData = $response->json('data');
        expect(count($responseData['main_content']))->toBe(1)
            ->and($responseData['main_content'][0]['title'])->toBe('Latest Products')
            ->and($responseData['main_content'][0]['type'])->toBe('DYNAMIC_LIST')
            ->and(count($responseData['main_content'][0]['content']['items']))->toBe(3);
    });

    it('can retrieve home page content with banner blocks', function (): void {
        $image = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('banner.jpg'))
            ->toDisk('public')
            ->upload();

        $bannerBlock = HomePageBlock::factory()->banner($image)->create([
            'title' => 'Hero Banner',
            'location' => 'hero',
            'order' => 1,
            'is_active' => true,
        ]);

        $response = $this->getJson(route('api.v1.shop.home-page-content'));

        $response->assertStatus(200);
        $responseData = $response->json('data');

        expect(count($responseData['hero']))->toBe(1)
            ->and($responseData['hero'][0]['title'])->toBe('Hero Banner')
            ->and($responseData['hero'][0]['type'])->toBe('BANNER')
            ->and($responseData['hero'][0]['content']['image_url'])->toBe($image->getUrl());
    });

    it('filters out inactive blocks', function (): void {
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

        $response = $this->getJson(route('api.v1.shop.home-page-content'));

        $response->assertStatus(200);
        $responseData = $response->json('data');

        expect(count($responseData['main_content']))->toBe(1)
            ->and($responseData['main_content'][0]['title'])->toBe('Active Block');
    });

    it('orders blocks correctly by location and order', function (): void {
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

        $response = $this->getJson(route('api.v1.shop.home-page-content'));

        $response->assertStatus(200);
        $responseData = $response->json('data');

        expect(count($responseData['hero']))->toBe(1)
            ->and(count($responseData['main_content']))->toBe(2)
            ->and($responseData['hero'][0]['title'])->toBe('Hero Block')
            ->and($responseData['main_content'][0]['title'])->toBe('Main Block 1')
            ->and($responseData['main_content'][1]['title'])->toBe('Main Block 2');
    });
});
