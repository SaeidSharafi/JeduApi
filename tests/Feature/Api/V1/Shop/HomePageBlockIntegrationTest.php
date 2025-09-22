<?php

declare(strict_types=1);

use App\Enums\DynamicListEntityTypeEnum;
use App\Enums\DynamicListSortByEnum;
use App\Enums\HomePageBlockTypeEnum;
use App\Models\Blog\BlogPost;
use App\Models\Category;
use App\Models\HomePageBlock;
use App\Models\Product;

use function Pest\Laravel\getJson;

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
    it('can retrieve home page blocks list when no blocks exist', function (): void {
        $response = getJson(route('api.v1.shop.home-page-blocks.index'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [],
            ]);

        $responseData = $response->json('data');
        expect($responseData)->toBeArray()
            ->and($responseData)->toHaveCount(0);
    });

    it('can retrieve blocks list and individual curated list blocks', function (): void {
        // Create some test categories and products
        $categories = Category::factory()->count(3)->create();
        $products   = Product::factory()->withDeliveryOptions()
            ->count(3)->create();

        // Create a MAIN_CATEGORIES block
        $categoryBlock = HomePageBlock::factory()->curatedList(
            $categories->pluck('id')->toArray(),
            HomePageBlockTypeEnum::MAIN_CATEGORIES
        )->create([
            'title'     => 'Main Categories',
            'location'  => 'main_content',
            'order'     => 1,
            'is_active' => true,
        ]);

        // Create a CURATED_LIST block
        $productBlock = HomePageBlock::factory()->curatedList(
            $products->pluck('id')->toArray(),
            HomePageBlockTypeEnum::CURATED_LIST
        )->create([
            'title'     => 'Featured Products',
            'location'  => 'main_content',
            'order'     => 2,
            'is_active' => true,
        ]);

        // Test blocks list
        $blocksResponse = getJson(route('api.v1.shop.home-page-blocks.index'));
        $blocksResponse->assertStatus(200);
        $blocksResponse->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'location',
                    'order',
                    'preset',
                ],
            ],
        ]);
        $blocksData = $blocksResponse->json('data');

        expect(count($blocksData))->toBe(2)
            ->and($blocksData[0]['id'])->toBe($categoryBlock->id)
            ->and($blocksData[0]['location'])->toBe('main_content')
            ->and($blocksData[1]['id'])->toBe($productBlock->id)
            ->and($blocksData[1]['location'])->toBe('main_content');

        // Test individual category block
        $categoryResponse = getJson(route('api.v1.shop.home-page-blocks.show', ['homePageBlock' => $categoryBlock->id]));
        $categoryResponse->assertStatus(200);
        $categoryData = $categoryResponse->json('data');

        expect($categoryData['title'])->toBe('Main Categories')
            ->and($categoryData['type'])->toBe('MAIN_CATEGORIES')
            ->and(count($categoryData['content']['items']))->toBe(3);

        // Test individual product block
        $productResponse = getJson(route('api.v1.shop.home-page-blocks.show', ['homePageBlock' => $productBlock->id]));
        $productResponse->assertStatus(200);
        $productData = $productResponse->json('data');

        expect($productData['title'])->toBe('Featured Products')
            ->and($productData['type'])->toBe('CURATED_LIST')
            ->and(count($productData['content']['items']))->toBe(3);
    });

    it('can retrieve individual dynamic list block', function (): void {
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
            'title'     => 'Latest Products',
            'location'  => 'main_content',
            'order'     => 1,
            'is_active' => true,
        ]);

        $response = getJson(route('api.v1.shop.home-page-blocks.show', ['homePageBlock' => $dynamicBlock->id]));

        $response->assertStatus(200);
        $responseData = $response->json('data');

        expect($responseData['title'])->toBe('Latest Products')
            ->and($responseData['type'])->toBe('DYNAMIC_LIST')
            ->and(count($responseData['content']['items']))->toBe(3);
    });

    it('can retrieve individual banner block', function (): void {
        $image = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('banner.jpg'))
            ->toDisk('public')
            ->upload();

        $bannerBlock = HomePageBlock::factory()->banner($image)->create([
            'title'     => 'Hero Banner',
            'location'  => 'hero',
            'order'     => 1,
            'is_active' => true,
        ]);

        $response = getJson(route('api.v1.shop.home-page-blocks.show', ['homePageBlock' => $bannerBlock->id]));

        $response->assertStatus(200);
        $responseData = $response->json('data');

        expect($responseData['title'])->toBe('Hero Banner')
            ->and($responseData['type'])->toBe('BANNER')
            ->and($responseData['content']['image_url'])->toBe($image->getUrl());
    });
    it('can retrieve dynamic block with BLOG type', function (): void {
        // Create some test blog posts
        BlogPost::factory()
            ->count(4)
            ->sequence([
                'title'      => 'First Post',
                'created_at' => now()->subDays(4),
            ], [
                'title'      => 'Second Post',
                'created_at' => now()->subDays(3),
            ], [
                'title'      => 'Third Post',
                'created_at' => now()->subDays(2),
            ], [
                'title'      => 'Fourth Post',
                'created_at' => now()->subDay(),
            ])
            ->create([
                'status' => App\Enums\PublicationStatusEnum::PUBLISHED,
            ]);

        // Create a DYNAMIC_LIST block for BLOG posts
        $dynamicBlogBlock = HomePageBlock::factory()->dynamicList(
            DynamicListEntityTypeEnum::BLOG_POST,
            DynamicListSortByEnum::CREATED_AT_DESC,
            2
        )->create([
            'title'     => 'Latest Blog Posts',
            'location'  => 'main_content',
            'order'     => 1,
            'is_active' => true,
        ]);

        $response = getJson(route('api.v1.shop.home-page-blocks.show', ['homePageBlock' => $dynamicBlogBlock->id]));

        $response->assertStatus(200);
        $responseData = $response->json('data');

        $fourthPost = BlogPost::where('title', 'Fourth Post')->first();
        $thirdPost  = BlogPost::where('title', 'Third Post')->first();
        expect($responseData['title'])->toBe('Latest Blog Posts')
            ->and($responseData['type'])->toBe('DYNAMIC_LIST')
            ->and(count($responseData['content']['items']))->toBe(2)
            ->and($responseData['content']['items'][0])
            ->toHaveKeys([
                'id',
                'title',
                'slug',
                'excerpt',
                'author',
                'reviews_count',
                'average_rating',
                'published_at',
                'thumbnail_url',
            ])
            ->and($responseData['content']['items'][0]['title'])->toBe('Fourth Post')
            ->and($responseData['content']['items'][1]['title'])->toBe('Third Post')
            ->and($responseData['content']['items'][0]['id'])->toBe($fourthPost->id)
            ->and($responseData['content']['items'][1]['id'])->toBe($thirdPost->id)
            ->and($responseData['content']['items'][0]['excerpt'])->toBe($fourthPost->excerpt)
            ->and($responseData['content']['items'][0]['author']['name'])->toBe($fourthPost->author->name)
            ->and($responseData['content']['items'][0]['published_at'])->toBe($this->toJalalitString($fourthPost->published_at));
    });

    it('filters out inactive blocks in blocks list', function (): void {
        // Create active and inactive blocks
        HomePageBlock::factory()->banner()->create([
            'title'     => 'Active Block',
            'location'  => 'main_content',
            'is_active' => true,
        ]);

        HomePageBlock::factory()->banner()->create([
            'title'     => 'Inactive Block',
            'location'  => 'main_content',
            'is_active' => false,
        ]);

        $response = getJson(route('api.v1.shop.home-page-blocks.index'));

        $response->assertStatus(200);
        $responseData = $response->json('data');

        expect(count($responseData))->toBe(1);
    });

    it('orders blocks correctly by location and order', function (): void {
        HomePageBlock::factory()->banner()->create([
            'title'     => 'Main Block 2',
            'location'  => 'main_content',
            'order'     => 2,
            'is_active' => true,
        ]);

        HomePageBlock::factory()->banner()->create([
            'title'     => 'Hero Block',
            'location'  => 'hero',
            'order'     => 1,
            'is_active' => true,
        ]);

        HomePageBlock::factory()->banner()->create([
            'title'     => 'Main Block 1',
            'location'  => 'main_content',
            'order'     => 1,
            'is_active' => true,
        ]);

        $response = getJson(route('api.v1.shop.home-page-blocks.index'));

        $response->assertStatus(200);
        $responseData = $response->json('data');

        // We expect 3 blocks:
        // 1. Hero Block (location: 'hero', order: 1)
        // 2. Main Block 1 (location: 'main_content', order: 1)
        // 3. Main Block 2 (location: 'main_content', order: 2)
        // Ordered by location first (hero comes before main_content), then by order
        expect(count($responseData))->toBe(3)
            ->and($responseData[0]['location'])->toBe('hero')
            ->and($responseData[1]['location'])->toBe('main_content')
            ->and($responseData[2]['location'])->toBe('main_content');

        // Check ordering within main_content section
        // The first main_content block should have order 1, second should have order 2
        // We can verify this by checking the IDs since they're created in that order
    });

    it('can retrieve home page blocks list', function (): void {
        // Create test blocks
        $block1 = HomePageBlock::factory()->banner()->create([
            'title'     => 'Banner Block',
            'location'  => 'hero',
            'order'     => 1,
            'is_active' => true,
        ]);

        $block2 = HomePageBlock::factory()->curatedList(
            [],
            HomePageBlockTypeEnum::CURATED_LIST
        )->create([
            'title'     => 'Products Block',
            'location'  => 'main_content',
            'order'     => 1,
            'is_active' => true,
        ]);

        $response = getJson(route('api.v1.shop.home-page-blocks.index'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'location',
                        'preset',
                    ],
                ],
            ]);

        $responseData = $response->json('data');
        expect(count($responseData))->toBe(2)
            ->and($responseData[0]['id'])->toBe($block1->id)
            ->and($responseData[0]['location'])->toBe('hero')
            ->and($responseData[1]['id'])->toBe($block2->id)
            ->and($responseData[1]['location'])->toBe('main_content');
    });

    it('can retrieve single home page block', function (): void {
        // Create test products
        $products = Product::factory()->withDeliveryOptions()
            ->count(2)->create();

        // Create a curated list block
        $block = HomePageBlock::factory()->curatedList(
            $products->pluck('id')->toArray(),
            HomePageBlockTypeEnum::CURATED_LIST
        )->create([
            'title'     => 'Featured Products',
            'location'  => 'main_content',
            'order'     => 1,
            'is_active' => true,
        ]);

        $response = getJson(route('api.v1.shop.home-page-blocks.show', ['homePageBlock' => $block->id]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'type',
                    'title',
                    'location',
                    'content' => [
                        'items',
                        'preset',
                    ],
                ],
            ]);

        $responseData = $response->json('data');
        expect($responseData['id'])->toBe($block->id)
            ->and($responseData['type'])->toBe('CURATED_LIST')
            ->and($responseData['title'])->toBe('Featured Products')
            ->and($responseData['location'])->toBe('main_content')
            ->and(count($responseData['content']['items']))->toBe(2);
    });

    it('returns 404 for non-existent home page block', function (): void {
        $response = getJson(route('api.v1.shop.home-page-blocks.show', ['homePageBlock' => 999]));

        $response->assertStatus(404);
    });

    it('filters only active blocks in blocks list', function (): void {
        // Create active and inactive blocks
        HomePageBlock::factory()->banner()->create([
            'title'     => 'Active Block',
            'location'  => 'hero',
            'is_active' => true,
        ]);

        HomePageBlock::factory()->banner()->create([
            'title'     => 'Inactive Block',
            'location'  => 'hero',
            'is_active' => false,
        ]);

        $response = getJson(route('api.v1.shop.home-page-blocks.index'));

        $response->assertStatus(200);
        $responseData = $response->json('data');
        expect(count($responseData))->toBe(1);
    });
});
