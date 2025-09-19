<?php

declare(strict_types=1);

use App\Actions\Shop\GetHomePageContentAction;
use App\Data\Shop\HomePageContentData;
use App\Enums\DeliveryMethodEnum;
use App\Enums\DynamicListEntityTypeEnum;
use App\Enums\DynamicListSortByEnum;
use App\Enums\FulfillmentTypeEnum;
use App\Enums\HomePageBlockTypeEnum;
use App\Enums\PublicationStatusEnum;
use App\Models\Blog\BlogPost;
use App\Models\HomePageBlock;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Services\ProductPriceService;
use App\Services\RequestDataCacheService;

uses(\Tests\Traits\CreatesModelsWithCachedData::class);
beforeEach(function () {
    \Illuminate\Support\Facades\Storage::fake('public');
    $this->image = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('image.jpg'))
        ->toDisk('public')
        ->upload();
});

describe('GetHomePageContentAction', function () {

    it('can handle empty blocks', function () {
        $action = app(GetHomePageContentAction::class);
        $result = $action->handle();

        expect($result)->toBeInstanceOf(HomePageContentData::class)
            ->and($result->hero)->toBeArray()
            ->and($result->main_content)->toBeArray()
            ->and(count($result->hero))->toBe(0)
            ->and(count($result->main_content))->toBe(0);
    });

    it('can handle curated list blocks', function () {
        // Create test data
        $categories = Category::factory()->count(3)->create();
        $products = Product::factory()->withDeliveryOptions()
            ->count(3)->create();

        // Create blocks
        HomePageBlock::factory()->curatedList(
            $categories->pluck('id')->toArray(),
            HomePageBlockTypeEnum::MAIN_CATEGORIES
        )->create([
            'title'     => 'Main Categories',
            'location'  => 'main_content',
            'order'     => 1,
            'is_active' => true,
        ]);

        HomePageBlock::factory()->curatedList(
            $products->pluck('id')->toArray(),
            HomePageBlockTypeEnum::CURATED_LIST
        )->create([
            'title'     => 'Featured Products',
            'location'  => 'main_content',
            'order'     => 2,
            'is_active' => true,
        ]);

        $action = app()->make(GetHomePageContentAction::Class);
        $result = $action->handle();

        expect($result)->toBeInstanceOf(HomePageContentData::class)
            ->and(count($result->main_content))->toBe(2)
            ->and($result->main_content[0]['title'])->toBe('Main Categories')
            ->and($result->main_content[0]['type'])->toBe('MAIN_CATEGORIES')
            ->and($result->main_content[1]['title'])->toBe('Featured Products')
            ->and($result->main_content[1]['type'])->toBe('CURATED_LIST')
            ->and(count($result->main_content[0]['content']['items']))->toBe(3)
            ->and(count($result->main_content[1]['content']['items']))->toBe(3);
    });
    it('can handle dynamic list blocks (Product) wiht generated price', function () {
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
                    'fulfillment_type' => FulfillmentTypeEnum::OFFILNE_SERVICE,
                    'delivery_method'  => DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER,
                    'price'            => 20000,
                ],
            ]);

        $mockedPriceService = Mockery::mock(ProductPriceService::class);
        $mockedPriceService->shouldNotReceive('getPriceDataForProduct');

        $product = $this->createWithPriceCache($product);
        // Create dynamic list block
        HomePageBlock::factory()->dynamicList(
            DynamicListEntityTypeEnum::ALL_PRODUCTS,
            DynamicListSortByEnum::CREATED_AT_DESC,
            3
        )->create([
            'title'     => 'Latest Products',
            'location'  => 'main_content',
            'order'     => 1,
            'is_active' => true,
        ]);
        $action = new GetHomePageContentAction($mockedPriceService, app(RequestDataCacheService::class));

        $result = $action->handle();

        expect($result)->toBeInstanceOf(HomePageContentData::class)
            ->and(count($result->main_content))->toBe(1)
            ->and($result->main_content[0]['title'])->toBe('Latest Products')
            ->and($result->main_content[0]['type'])->toBe('DYNAMIC_LIST')
            ->and(count($result->main_content[0]['content']['items']))->toBe(1)
            ->and($result->main_content[0]['content']['items'][0]['id'])->toBe($product->id)
            ->and($result->main_content[0]['content']['items'][0]['price'])->toBe(20000)
            ->and($result->main_content[0]['content']['items'][0]['original_price'])->toBe(20000);
    });
    it('can handle dynamic list blocks (Product) wihtout generated price', function () {
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
                    'fulfillment_type' => FulfillmentTypeEnum::OFFILNE_SERVICE,
                    'delivery_method'  => DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER,
                    'price'            => 20000,
                ],
            ])
            ->create();

        // Create dynamic list block
        HomePageBlock::factory()->dynamicList(
            DynamicListEntityTypeEnum::ALL_PRODUCTS,
            DynamicListSortByEnum::CREATED_AT_DESC,
            3
        )->create([
            'title'     => 'Latest Products',
            'location'  => 'main_content',
            'order'     => 1,
            'is_active' => true,
        ]);
        $action = app()->Make(GetHomePageContentAction::Class);
        $result = $action->handle();
        $product->refresh();
        expect($product->price_data_cache)->toBeNull()
            ->and($result)->toBeInstanceOf(HomePageContentData::class)
            ->and(count($result->main_content))->toBe(1)
            ->and($result->main_content[0]['title'])->toBe('Latest Products')
            ->and($result->main_content[0]['type'])->toBe('DYNAMIC_LIST')
            ->and(count($result->main_content[0]['content']['items']))->toBe(1)
            ->and($result->main_content[0]['content']['items'][0]['id'])->toBe($product->id)
            ->and($result->main_content[0]['content']['items'][0]['price'])->toBe(20000)
            ->and($result->main_content[0]['content']['items'][0]['original_price'])->toBe(20000);

    });
    it('can handle dynamic list blocks (Product)', function () {
        // Create test data
        Product::factory()
            ->withDeliveryOptions()
            ->count(5)->create();

        // Create dynamic list block
        HomePageBlock::factory()->dynamicList(
            DynamicListEntityTypeEnum::ALL_PRODUCTS,
            DynamicListSortByEnum::CREATED_AT_DESC,
            3
        )->create([
            'title'     => 'Latest Products',
            'location'  => 'main_content',
            'order'     => 1,
            'is_active' => true,
        ]);

        $action = app()->Make(GetHomePageContentAction::Class);
        $result = $action->handle();

        expect($result)->toBeInstanceOf(HomePageContentData::class)
            ->and(count($result->main_content))->toBe(1)
            ->and($result->main_content[0]['title'])->toBe('Latest Products')
            ->and($result->main_content[0]['type'])->toBe('DYNAMIC_LIST')
            ->and(count($result->main_content[0]['content']['items']))->toBe(3);
    });

    it('can handle dynamic list blocks (BlogPost)', function () {
        // Create test data
        BlogPost::factory()->count(5)->create(
            [
                'status' => PublicationStatusEnum::PUBLISHED
            ]
        );
        // Create dynamic list block
        HomePageBlock::factory()->dynamicList(
            DynamicListEntityTypeEnum::BLOG_POST,
            DynamicListSortByEnum::CREATED_AT_DESC,
            2
        )->create([
            'title'     => 'Latest Blog Posts',
            'location'  => 'main_content',
            'order'     => 1,
            'is_active' => true,
        ]);

        $action = app()->Make(GetHomePageContentAction::Class);
        $result = $action->handle();

        expect($result)->toBeInstanceOf(HomePageContentData::class)
            ->and(count($result->main_content))->toBe(1)
            ->and($result->main_content[0]['title'])->toBe('Latest Blog Posts')
            ->and($result->main_content[0]['type'])->toBe('DYNAMIC_LIST')
            ->and(count($result->main_content[0]['content']['items']))->toBe(2);
    });
    //test sorts
    it('can handle dynamic list blocks with different sorts', function ($sortOption) {
        // Create test data this needs to be deterministic
        // so we have to create Product and ProductDelveryOption wiht some Order and OrderItem
        // manually one by one
        for ($i = 1; $i <= 5; $i++) {
            $product = Product::factory()
                ->create([
                    'name'        => "Product $i",
                    'created_at'  => now()->subDays(5 - $i),
                    'updated_at'  => now()->subDays(5 - $i),
                    'is_featured' => $i % 2 == 0,
                ]);
            ProductDeliveryOption::factory()
                ->create([
                    'product_id'              => $product->id,
                    'price'                   => 10000 * $i,
                    'is_prepayment_available' => $i % 2 == 0,
                    'prepayment_amount'       => $i % 2 == 0 ? 5000 * $i : null,
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
        HomePageBlock::factory()->dynamicList(
            DynamicListEntityTypeEnum::ALL_PRODUCTS,
            $sortOption,
            3
        )->create([
            'title'     => 'Sorted Products',
            'location'  => 'main_content',
            'order'     => 1,
            'is_active' => true,
        ]);

        $action = app()->Make(GetHomePageContentAction::Class);
        $result = $action->handle();
        // we need to check if the products are sorted correctly
        $sortedProducts = match ($sortOption) {
            DynamicListSortByEnum::CREATED_AT_ASC => Product::orderBy('created_at', 'asc')->take(3)->get(),
            DynamicListSortByEnum::CREATED_AT_DESC => Product::orderBy('created_at', 'desc')->take(3)->get(),
            DynamicListSortByEnum::UPDATED_AT_ASC => Product::orderBy('updated_at', 'asc')->take(3)->get(),
            DynamicListSortByEnum::UPDATED_AT_DESC => Product::orderBy('updated_at', 'desc')->take(3)->get(),
            DynamicListSortByEnum::NAME_ASC => Product::orderBy('name', 'asc')->take(3)->get(),
            DynamicListSortByEnum::NAME_DESC => Product::orderBy('name', 'desc')->take(3)->get(),
            DynamicListSortByEnum::POPULAR => Product::withCount('orderItems')->orderBy('order_items_count', 'desc')
                ->take(3)->get(),
            DynamicListSortByEnum::FEATURED => Product::where('is_featured', true)->orderBy('created_at', 'desc')
                ->take(3)->get(),
        };

        $resultProductIds = array_map(fn($item) => $item['id'], $result->main_content[0]['content']['items']);
        $expectedProductIds = $sortedProducts->pluck('id')->toArray();

        expect($resultProductIds)->toBe($expectedProductIds)
            ->and($result)->toBeInstanceOf(HomePageContentData::class)
            ->and(count($result->main_content))->toBe(1)
            ->and($result->main_content[0]['title'])->toBe('Sorted Products')
            ->and($result->main_content[0]['type'])->toBe('DYNAMIC_LIST')
            ->and(count($result->main_content[0]['content']['items']))
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
    it('can handle dynamic list blocks with category filter', function () {
        // Create test data
        $categories = Category::factory()->count(2)->create();
        $productsInCategory1 = Product::factory()
            ->withDeliveryOptions()
            ->count(3)
            ->create()
            ->each(fn($product) => $product->categories()->attach($categories[0]->id));
        $productsInCategory2 = Product::factory()
            ->withDeliveryOptions()
            ->count(2)
            ->create()
            ->each(fn($product) => $product->categories()->attach($categories[1]->id));

        // Create dynamic list block with category filter
        HomePageBlock::factory()->dynamicList(
            DynamicListEntityTypeEnum::ALL_PRODUCTS,
            DynamicListSortByEnum::CREATED_AT_DESC,
            3,
            [$categories[0]->id] // Filter by category 1
        )->create([
            'title'     => 'Category 1 Products',
            'location'  => 'main_content',
            'order'     => 1,
            'is_active' => true,
        ]);

        $action = app()->Make(GetHomePageContentAction::Class);
        $result = $action->handle();

        expect($result)->toBeInstanceOf(HomePageContentData::class)
            ->and(count($result->main_content))->toBe(1)
            ->and($result->main_content[0]['title'])->toBe('Category 1 Products')
            ->and($result->main_content[0]['type'])->toBe('DYNAMIC_LIST')
            ->and(count($result->main_content[0]['content']['items']))->toBe(3)
            ->and(collect($result->main_content[0]['content']['items'])->pluck('id')->toArray())
            ->toBe($productsInCategory1->sortByDesc('created_at')->pluck('id')->take(3)->toArray());
    });

    it('can handle dynamic list blocks for blogs with popularity sort option', function () {
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
        //beacuse we do not have view counts we sort he blogs by created at
        $action = app()->Make(GetHomePageContentAction::Class);
        HomePageBlock::factory()->dynamicList(
            DynamicListEntityTypeEnum::BLOG_POST,
            DynamicListSortByEnum::POPULAR,
            3
        )->create([
            'title'     => 'Popular Blog Posts',
            'location'  => 'main_content',
            'order'     => 1,
            'is_active' => true,
        ]);
        $result = $action->handle();

        expect($result)->toBeInstanceOf(HomePageContentData::class)
            ->and(count($result->main_content))->toBe(1)
            ->and($result->main_content[0]['title'])->toBe('Popular Blog Posts')
            ->and($result->main_content[0]['type'])->toBe('DYNAMIC_LIST')
            ->and(count($result->main_content[0]['content']['items']))->toBe(3)
            ->and(collect($result->main_content[0]['content']['items'])->pluck('id')->toArray())
            ->toBe($blogPosts->sortByDesc('created_at')->pluck('id')->take(3)->toArray());
    });

    it('can handle banner blocks', function () {
        // Create banner block
        $banner = HomePageBlock::factory()->banner($this->image)->create([
            'title'     => 'Welcome Banner',
            'location'  => 'hero',
            'order'     => 1,
            'is_active' => true,
        ]);
        $banner->attachMedia($this->image, 'image');
        $action = app()->Make(GetHomePageContentAction::Class);
        $result = $action->handle();

        expect($result)->toBeInstanceOf(HomePageContentData::class)
            ->and(count($result->hero))->toBe(1)
            ->and($result->hero[0]['title'])->toBe('Welcome Banner')
            ->and($result->hero[0]['type'])->toBe('BANNER')
            ->and($result->hero[0]['content']['image_url'])->toBe($this->image->getUrl())
            ->and($result->hero[0]['content']['action'])->toBe($banner->content['action'])
            ->and($result->hero[0]['content']['action_title'])->toBe($banner->content['action_title'])
            ->and($result->hero[0]['content']['content'])->toBe($banner->content['content'])
            ->and($result->hero[0]['content']['preset'])->toBe($banner->content['preset']);

    });

    it('can handle webinar banner blocks', function () {
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

        $action = app()->Make(GetHomePageContentAction::Class);
        $result = $action->handle();

        expect($result)->toBeInstanceOf(HomePageContentData::class)
            ->and(count($result->hero))->toBe(1)
            ->and($result->hero[0]['title'])->toBe('Upcoming Webinar')
            ->and($result->hero[0]['type'])->toBe('WEBINAR_BANNER')
            ->and($result->hero[0]['content']['image_url'])->toBe($this->image->getUrl())
            ->and($result->hero[0]['content']['text'])->toBe($webinarBanner->content['text'])
            ->and($result->hero[0]['content']['product']['id'])->toBe($product->id)
            ->and($result->hero[0]['content']['product']['name'])->toBe($product->name)
            ->and($result->hero[0]['content']['product']['price'])->toBe(100000)
            ->and($result->hero[0]['content']['product']['original_price'])->toBe(100000);
    });

    it('filters inactive blocks', function () {
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

        $action = app()->Make(GetHomePageContentAction::Class);
        $result = $action->handle();

        expect(count($result->main_content))->toBe(1)
            ->and($result->main_content[0]['title'])->toBe('Active Block');
    });

    it('orders blocks correctly', function () {
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

        $action = app()->Make(GetHomePageContentAction::Class);
        $result = $action->handle();

        expect(count($result->hero))->toBe(1)
            ->and(count($result->main_content))->toBe(2)
            ->and($result->hero[0]['title'])->toBe('Hero Block')
            ->and($result->main_content[0]['title'])->toBe('Main Block 1')
            ->and($result->main_content[1]['title'])->toBe('Main Block 2');
    });

    it('handle laoding media for products in curated list', function () {
        // Create test data
        $course = \App\Models\Course::factory()->create();
        $product = Product::factory()->withDeliveryOptions()->create([
            'productable_type' => \App\Enums\ProductableEnum::COURSE,
            'productable_id'   => $course->id,
        ]);
        $course->attachMedia($this->image, 'cover');

        // Create blocks
        HomePageBlock::factory()
            ->curatedList([$product->id])
            ->create([
                'title'     => 'Featured Products',
                'location'  => 'main_content',
                'order'     => 1,
                'is_active' => true,
            ]);

        $action = app()->make(GetHomePageContentAction::Class);
        $result = $action->handle();

        expect($result)->toBeInstanceOf(HomePageContentData::class)
            ->and(count($result->main_content))->toBe(1)
            ->and($result->main_content[0]['title'])->toBe('Featured Products')
            ->and($result->main_content[0]['type'])->toBe('CURATED_LIST')
            ->and(count($result->main_content[0]['content']['items']))->toBe(1)
            ->and($result->main_content[0]['content']['items'][0]['id'])->toBe($product->id)
            ->and($result->main_content[0]['content']['items'][0]['cover_image_url'])->toBe($this->image->getUrl());
    });
});
