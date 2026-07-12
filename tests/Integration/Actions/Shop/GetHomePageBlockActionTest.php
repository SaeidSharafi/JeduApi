<?php

declare(strict_types=1);

use App\Actions\Shop\GetHomePageBlockAction;
use App\Data\Shop\HomePage\HomePageBlockData;
use App\Enums\Content\DynamicListEntityTypeEnum;
use App\Enums\Content\DynamicListSortByEnum;
use App\Enums\Content\HomePageBlockTypeEnum;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Models\Blog\BlogPost;
use App\Models\Category;
use App\Models\HomePageBlock;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Services\ProductPriceService;
use App\Services\RequestDataCacheService;

uses(Tests\Support\Traits\CreatesModelsWithCachedData::class);
beforeEach(function (): void {
    Illuminate\Support\Facades\Storage::fake('public');
    $this->image = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('image.jpg'))
        ->toDisk('public')
        ->upload();
});

describe('GetHomePageBlockAction', function (): void {

    it('can handle curated list blocks', function (): void {
        // Create test data
        $categories = Category::factory()->count(3)->create();
        $products   = Product::factory()->withDeliveryOptions()
            ->count(3)->create();

        // Create blocks
        $block = HomePageBlock::factory()->curatedList(
            $categories->pluck('id')->toArray(),
            HomePageBlockTypeEnum::MAIN_CATEGORIES
        )->create([
            'title'     => 'Main Categories',
            'location'  => 'content',
            'order'     => 1,
            'is_active' => true,
        ]);
        $action = app()->make(GetHomePageBlockAction::class);
        $result = $action->handle($block);

        expect($result)->toBeInstanceOf(HomePageBlockData::class)
            ->and(count($result->content))->toBe(2)
            ->and($result->title)->toBe('Main Categories')
            ->and($result->type)->toBe('MAIN_CATEGORIES')
            ->and(count($result->content['items']))->toBe(3);
        $block = HomePageBlock::factory()->curatedList(
            $products->pluck('id')->toArray(),
            HomePageBlockTypeEnum::CURATED_LIST
        )->create([
            'title'     => 'Featured Products',
            'location'  => 'content',
            'order'     => 2,
            'is_active' => true,
        ]);

        $result = $action->handle($block);

        expect($result)->toBeInstanceOf(HomePageBlockData::class)
            ->and(count($result->content))->toBe(2)
            ->and($result->title)->toBe('Featured Products')
            ->and($result->type)->toBe('CURATED_LIST')
            ->and(count($result->content['items']))->toBe(3);
    });
    it('can handle dynamic list blocks (Product) wiht generated price', function (): void {
        // Create test data
        $product = Product::factory()
            ->withDeliveryOptions(realData: [
                [
                    'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE,
                    'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE,
                    'price'            => 20000,
                ],
                [
                    'fulfillment_type' => FulfillmentTypeEnum::IN_PERSON_SERVICE,
                    'delivery_method'  => DeliveryMethodEnum::IN_PERSON,
                    'price'            => 20000,
                ],
                [
                    'fulfillment_type' => FulfillmentTypeEnum::OFFLINE_SERVICE,
                    'delivery_method'  => DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER,
                    'price'            => 20000,
                ],
            ]);

        $mockedPriceService = Mockery::mock(ProductPriceService::class)->makePartial();
        $mockedPriceService->shouldNotReceive('calculatePriceDataForProduct');

        $product = $this->createWithPriceCache($product);
        // Create dynamic list block
        $block = HomePageBlock::factory()->dynamicList(
            DynamicListEntityTypeEnum::ALL_PRODUCTS,
            DynamicListSortByEnum::CREATED_AT_DESC,
            3
        )->create([
            'title'     => 'Latest Products',
            'location'  => 'content',
            'order'     => 1,
            'is_active' => true,
        ]);
        $action = new GetHomePageBlockAction($mockedPriceService, app(RequestDataCacheService::class));

        $result = $action->handle($block);

        expect($result)->toBeInstanceOf(HomePageBlockData::class)
            ->and($result->title)->toBe('Latest Products')
            ->and($result->type)->toBe('DYNAMIC_LIST')
            ->and(count($result->content['items']))->toBe(1)
            ->and($result->content['items'][0]['slug'])->toBe($product->slug)
            ->and($result->content['items'][0]['price'])->toBe(20000)
            ->and($result->content['items'][0]['original_price'])->toBe(20000);
    });
    it('can handle dynamic list blocks (Product) wihtout generated price', function (): void {
        $product = Product::factory()
            ->withDeliveryOptions(realData: [
                [
                    'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE,
                    'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE,
                    'price'            => 20000,
                ],
                [
                    'fulfillment_type' => FulfillmentTypeEnum::IN_PERSON_SERVICE,
                    'delivery_method'  => DeliveryMethodEnum::IN_PERSON,
                    'price'            => 20000,
                ],
                [
                    'fulfillment_type' => FulfillmentTypeEnum::OFFLINE_SERVICE,
                    'delivery_method'  => DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER,
                    'price'            => 20000,
                ],
            ])
            ->create();

        // Create dynamic list block
        $block = HomePageBlock::factory()->dynamicList(
            DynamicListEntityTypeEnum::ALL_PRODUCTS,
            DynamicListSortByEnum::CREATED_AT_DESC,
            3
        )->create([
            'title'     => 'Latest Products',
            'location'  => 'content',
            'order'     => 1,
            'is_active' => true,
        ]);
        $action = app()->Make(GetHomePageBlockAction::class);
        $result = $action->handle($block);
        $product->refresh();
        expect($product->price_data_cache)->toBeNull()
            ->and($result)->toBeInstanceOf(HomePageBlockData::class)
            ->and($result->title)->toBe('Latest Products')
            ->and($result->type)->toBe('DYNAMIC_LIST')
            ->and(count($result->content['items']))->toBe(1)
            ->and($result->content['items'][0]['slug'])->toBe($product->slug)
            ->and($result->content['items'][0]['price'])->toBe(20000)
            ->and($result->content['items'][0]['original_price'])->toBe(20000);

    });
    it('can handle dynamic list blocks (Product)', function (): void {
        // Create test data
        Product::factory()
            ->withDeliveryOptions()
            ->count(5)->create();

        // Create dynamic list block
        $block = HomePageBlock::factory()->dynamicList(
            DynamicListEntityTypeEnum::ALL_PRODUCTS,
            DynamicListSortByEnum::CREATED_AT_DESC,
            3
        )->create([
            'title'     => 'Latest Products',
            'location'  => 'content',
            'order'     => 1,
            'is_active' => true,
        ]);

        $action = app()->Make(GetHomePageBlockAction::class);
        $result = $action->handle($block);

        expect($result)->toBeInstanceOf(HomePageBlockData::class)

            ->and($result->title)->toBe('Latest Products')
            ->and($result->type)->toBe('DYNAMIC_LIST')
            ->and(count($result->content['items']))->toBe(3);
    });

    it('can handle dynamic list blocks (BlogPost)', function (): void {
        // Create test data
        BlogPost::factory()->count(5)->create(
            [
                'status' => PublicationStatusEnum::PUBLISHED,
            ]
        );
        // Create dynamic list block
        $block = HomePageBlock::factory()->dynamicList(
            DynamicListEntityTypeEnum::BLOG_POST,
            DynamicListSortByEnum::CREATED_AT_DESC,
            2
        )->create([
            'title'     => 'Latest Blog Posts',
            'location'  => 'content',
            'order'     => 1,
            'is_active' => true,
        ]);

        $action = app()->Make(GetHomePageBlockAction::class);
        $result = $action->handle($block);

        expect($result)->toBeInstanceOf(HomePageBlockData::class)

            ->and($result->title)->toBe('Latest Blog Posts')
            ->and($result->type)->toBe('DYNAMIC_LIST')
            ->and(count($result->content['items']))->toBe(2);
    });
    // test sorts
    it('can handle dynamic list blocks with different sorts', function ($sortOption): void {
        // Create test data this needs to be deterministic
        // so we have to create Product and ProductDelveryOption wiht some Order and OrderItem
        // manually one by one
        for ($i = 1; $i <= 5; $i++) {
            $product = Product::factory()
                ->create([
                    'name'        => "Product $i",
                    'created_at'  => now()->subDays(5 - $i),
                    'updated_at'  => now()->subDays(5 - $i),
                    'is_featured' => $i % 2 === 0,
                ]);
            ProductDeliveryOption::factory()
                ->create([
                    'product_id'              => $product->id,
                    'price'                   => 10000 * $i,
                    'is_prepayment_available' => $i % 2 === 0,
                    'prepayment_amount'       => $i % 2 === 0 ? 5000 * $i : null,
                ]);
            $product->refresh();
            // Create some orders and order items to affect popularity
            for ($j = 0; $j < $i; $j++) {
                $order = Order::factory()->create();
                OrderItem::factory()
                    ->create([
                        'order_id'                   => $order->id,
                        'product_delivery_option_id' => $product->productDeliveryOptions->first()->id,
                    ]);
            }
        }

        // Create dynamic list block
        $block = HomePageBlock::factory()->dynamicList(
            DynamicListEntityTypeEnum::ALL_PRODUCTS,
            $sortOption,
            3
        )->create([
            'title'     => 'Sorted Products',
            'location'  => 'content',
            'order'     => 1,
            'is_active' => true,
        ]);

        $action = app()->Make(GetHomePageBlockAction::class);
        $result = $action->handle($block);
        // we need to check if the products are sorted correctly
        $sortedProducts = match ($sortOption) {
            DynamicListSortByEnum::CREATED_AT_ASC  => Product::orderBy('created_at', 'asc')->take(3)->get(),
            DynamicListSortByEnum::CREATED_AT_DESC => Product::orderBy('created_at', 'desc')->take(3)->get(),
            DynamicListSortByEnum::UPDATED_AT_ASC  => Product::orderBy('updated_at', 'asc')->take(3)->get(),
            DynamicListSortByEnum::UPDATED_AT_DESC => Product::orderBy('updated_at', 'desc')->take(3)->get(),
            DynamicListSortByEnum::NAME_ASC        => Product::orderBy('name', 'asc')->take(3)->get(),
            DynamicListSortByEnum::NAME_DESC       => Product::orderBy('name', 'desc')->take(3)->get(),
            DynamicListSortByEnum::POPULAR         => Product::withCount('orderItems')->orderBy('order_items_count', 'desc')
                ->take(3)->get(),
            DynamicListSortByEnum::FEATURED => Product::where('is_featured', true)->orderBy('created_at', 'desc')
                ->take(3)->get(),
        };

        $resultProductIds   = array_map(fn ($item) => $item['slug'], $result->content['items']);
        $expectedProductIds = $sortedProducts->pluck('slug')->toArray();

        expect($resultProductIds)->toBe($expectedProductIds)
            ->and($result)->toBeInstanceOf(HomePageBlockData::class)

            ->and($result->title)->toBe('Sorted Products')
            ->and($result->type)->toBe('DYNAMIC_LIST')
            ->and(count($result->content['items']))
            ->toBe($sortOption === DynamicListSortByEnum::FEATURED ? 2 : 3);

    })
        ->with([
            [DynamicListSortByEnum::CREATED_AT_ASC],
            [DynamicListSortByEnum::CREATED_AT_DESC],
            [DynamicListSortByEnum::UPDATED_AT_ASC],
            [DynamicListSortByEnum::UPDATED_AT_DESC],
            [DynamicListSortByEnum::NAME_ASC],
            [DynamicListSortByEnum::NAME_DESC],
            [DynamicListSortByEnum::POPULAR],
            [DynamicListSortByEnum::FEATURED],

        ]);
    it('can handle dynamic list blocks for featured blog posts', function (): void {
        // Create test data
        $blogPosts = BlogPost::factory()
            ->count(5)
            ->sequence(
                ['is_featured' => true, 'created_at' => now()->subDays(5), 'status' => PublicationStatusEnum::PUBLISHED],
                ['is_featured' => false, 'created_at' => now()->subDays(4), 'status' => PublicationStatusEnum::PUBLISHED],
                ['is_featured' => true, 'created_at' => now()->subDays(3), 'status' => PublicationStatusEnum::PUBLISHED],
                ['is_featured' => false, 'created_at' => now()->subDays(2), 'status' => PublicationStatusEnum::PUBLISHED],
                ['is_featured' => true, 'created_at' => now()->subDays(1), 'status' => PublicationStatusEnum::PUBLISHED],
            )
            ->create();
        $action = app()->Make(GetHomePageBlockAction::class);
        $block  = HomePageBlock::factory()->dynamicList(
            DynamicListEntityTypeEnum::BLOG_POST,
            DynamicListSortByEnum::FEATURED,
            3
        )->create([
            'title'     => 'Featured Blog Posts',
            'location'  => 'content',
            'order'     => 1,
            'is_active' => true,
        ]);
        $result = $action->handle($block);

        expect($result)->toBeInstanceOf(HomePageBlockData::class)

            ->and($result->title)->toBe('Featured Blog Posts')
            ->and($result->type)->toBe('DYNAMIC_LIST')
            ->and(count($result->content['items']))->toBe(3)
            ->and(collect($result->content['items'])->pluck('slug')->toArray())
            ->toBe($blogPosts->where('is_featured', true)
                ->sortByDesc('created_at')
                ->pluck('slug')
                ->take(3)
                ->toArray());
    });
    it('can handle dynamic list blocks with category filter', function (): void {
        // Create test data
        $category1          = Category::factory()->create();
        $category2          = Category::factory()->create();
        $productsInCategory1 = Product::factory()
            ->withDeliveryOptions()
            ->count(3)
            ->sequence(
                ['created_at' => now()->subDays(5)],
                ['created_at' => now()->subDays(4)],
                ['created_at' => now()->subDays(3)],
            )
            ->create()
            ->each(fn ($product) => $product->categories()->attach($category1->id));
        $productsInCategory2 = Product::factory()
            ->withDeliveryOptions()
            ->count(2)
            ->sequence(
                ['created_at' => now()->subDays(2)],
                ['created_at' => now()->subDay()],
            )
            ->create()
            ->each(fn ($product) => $product->categories()->attach($category2->id));

        // Create dynamic list block with category filter
        $block = HomePageBlock::factory()->dynamicList(
            DynamicListEntityTypeEnum::ALL_PRODUCTS,
            DynamicListSortByEnum::CREATED_AT_DESC,
            3,
            [$category1->id] // Filter by category 1
        )->create([
            'title'     => 'Category 1 Products',
            'location'  => 'content',
            'order'     => 1,
            'is_active' => true,
        ]);

        $action = app()->Make(GetHomePageBlockAction::class);
        $result = $action->handle($block);

        expect($result)->toBeInstanceOf(HomePageBlockData::class)

            ->and($result->title)->toBe('Category 1 Products')
            ->and($result->type)->toBe('DYNAMIC_LIST')
            ->and(count($result->content['items']))->toBe(3)
            ->and(collect($result->content['items'])->pluck('slug')->toArray())
            ->toBe($productsInCategory1->sortByDesc('created_at')->pluck('slug')->take(3)->toArray());
    });

    it('can handle dynamic list blocks for blogs with popularity sort option', function (): void {
        // Create test data
        $blogPosts = BlogPost::factory()
            ->count(5)
            ->sequence(
                ['created_at' => now()->subDays(5), 'status' => PublicationStatusEnum::PUBLISHED],
                ['created_at' => now()->subDays(4), 'status' => PublicationStatusEnum::PUBLISHED],
                ['created_at' => now()->subDays(3), 'status' => PublicationStatusEnum::PUBLISHED],
                ['created_at' => now()->subDays(2), 'status' => PublicationStatusEnum::PUBLISHED],
                ['created_at' => now()->subDays(1), 'status' => PublicationStatusEnum::PUBLISHED],
            )
            ->create();
        // beacuse we do not have view counts we sort he blogs by created at
        $action = app()->Make(GetHomePageBlockAction::class);
        $block  = HomePageBlock::factory()->dynamicList(
            DynamicListEntityTypeEnum::BLOG_POST,
            DynamicListSortByEnum::POPULAR,
            3
        )->create([
            'title'     => 'Popular Blog Posts',
            'location'  => 'content',
            'order'     => 1,
            'is_active' => true,
        ]);
        $result = $action->handle($block);

        expect($result)->toBeInstanceOf(HomePageBlockData::class)

            ->and($result->title)->toBe('Popular Blog Posts')
            ->and($result->type)->toBe('DYNAMIC_LIST')
            ->and(count($result->content['items']))->toBe(3)
            ->and(collect($result->content['items'])->pluck('slug')->toArray())
            ->toBe($blogPosts->sortByDesc('created_at')->pluck('slug')->take(3)->toArray());
    });

    it('can handle banner blocks', function (): void {
        // Create banner block
        $block = HomePageBlock::factory()->banner($this->image)->create([
            'title'     => 'Welcome Banner',
            'location'  => 'hero',
            'order'     => 1,
            'is_active' => true,
        ]);
        $block->attachMedia($this->image, 'image');
        $action = app()->Make(GetHomePageBlockAction::class);
        $result = $action->handle($block);

        expect($result)->toBeInstanceOf(HomePageBlockData::class)
            ->and($result->title)->toBe('Welcome Banner')
            ->and($result->type)->toBe('BANNER')
            ->and($result->content['image_url'])->toBe($this->image->getUrl())
            ->and($result->content['action'])->toBe($block->content['action'])
            ->and($result->content['action_title'])->toBe($block->content['action_title'])
            ->and($result->content['content'])->toBe($block->content['content'])
            ->and($result->content['preset'])->toBe($block->content['preset']);

    });

    it('can handle webinar banner blocks', function (): void {
        // Create a product for the webinar banner
        $product = Product::factory()->create();
        ProductDeliveryOption::factory()
            ->create([
                'product_id' => $product->id,
                'price'      => 100000,
            ]);
        // Create webinar banner block
        $webinarBanner = HomePageBlock::factory()->webinarBanner($this->image, $product->id)->create([
            'title'     => 'Upcoming Webinar',
            'location'  => 'hero',
            'order'     => 1,
            'is_active' => true,
        ]);
        $webinarBanner->attachMedia($this->image, 'image');

        $action = app()->Make(GetHomePageBlockAction::class);
        $result = $action->handle($webinarBanner);

        expect($result)->toBeInstanceOf(HomePageBlockData::class)
            ->and($result->title)->toBe('Upcoming Webinar')
            ->and($result->type)->toBe('WEBINAR_BANNER')
            ->and($result->content['image_url'])->toBe($this->image->getUrl())
            ->and($result->content['text'])->toBe($webinarBanner->content['text'])
            ->and($result->content['product']['slug'])->toBe($product->slug)
            ->and($result->content['product']['name'])->toBe($product->name)
            ->and($result->content['product']['price'])->toBe(100000)
            ->and($result->content['product']['original_price'])->toBe(100000);
    });

    it('handle loading media for products in curated list', function (): void {
        // Create test data
        $course  = App\Models\Course::factory()->create();
        $product = Product::factory()->withDeliveryOptions()->create([
            'productable_type' => App\Enums\Product\ProductableEnum::COURSE->value,
            'productable_id'   => $course->id,
        ]);
        $course->attachMedia($this->image, 'cover');

        // Create blocks
        $block = HomePageBlock::factory()
            ->curatedList([$product->id])
            ->create([
                'title'     => 'Featured Products',
                'location'  => 'content',
                'order'     => 1,
                'is_active' => true,
            ]);

        $action = app()->make(GetHomePageBlockAction::class);
        $result = $action->handle($block);

        expect($result)->toBeInstanceOf(HomePageBlockData::class)

            ->and($result->title)->toBe('Featured Products')
            ->and($result->type)->toBe('CURATED_LIST')
            ->and(count($result->content['items']))->toBe(1)
            ->and($result->content['items'][0]['slug'])->toBe($product->slug)
            ->and($result->content['items'][0]['thumbnail_url'])->toBe($course->thumbnail_url);
    });
});
