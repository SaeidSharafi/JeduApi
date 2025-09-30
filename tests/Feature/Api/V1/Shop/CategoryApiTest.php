<?php

use App\Enums\Product\ProductableEnum;
use App\Models\Product;

describe('CategoryController', function () {

    it('get lsit of categories', function () {
        $response = $this->getJson(route('api.v1.shop.categories.index'));
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'educational_calendar_url',
                    'color_scheme',
                    'icon_url',
                    'image_url',
                    'meta_title',
                    'meta_description',
                    'meta_keywords',
                    'products_count',
                ]
            ]
        ]);
    });

    it('get single category details', function () {
        $category = \App\Models\Category::factory()->create();
        $anotherCategory = \App\Models\Category::factory()->create();

        $courses = Product::factory()
            ->withCourse()
            ->withDeliveryOptions(1)
            ->count(5)
            ->create();
        $seminars = Product::factory()
            ->withSeminar()
            ->withDeliveryOptions(1)
            ->count(5)
            ->create();
        $assets = Product::factory()
            ->withDigitalAsset()
            ->withDeliveryOptions(1)
            ->count(5)
            ->create();
        $courses->load('productDeliveryOptions', 'term', 'vendor');

        $category->products()->attach($courses);
        $category->products()->attach($seminars);
        $category->products()->attach($assets);
        $anotherCategory->products()->attach($courses->take(2));
        $anotherCategory->products()->attach($seminars->take(2));
        $anotherCategory->products()->attach($assets->take(2));

        $response = $this->getJson(route('api.v1.shop.categories.show', ['category' => $category->slug]));
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'name',
                'slug',
                'description',
                'educational_calendar_url',
                'color_scheme',
                'icon_url',
                'image_url',
                'meta_title',
                'meta_description',
                'meta_keywords',
                'courses'        => [
                    '*' => [
                        'slug',
                        'name',
                        'price',
                        'original_price',
                        'price_range',
                        'has_discount',
                        'discount_percent',
                        'is_free',
                        'is_featured',
                        'product_type',
                        'thumbnail_url',
                        'teachers',
                        'reviews_count',
                        'average_rating',
                        'price_data',
                    ]
                ],
                'seminars'       => [
                    '*' => [
                        'slug',
                        'name',
                        'price',
                        'original_price',
                        'price_range',
                        'has_discount',
                        'discount_percent',
                        'is_free',
                        'is_featured',
                        'product_type',
                        'thumbnail_url',
                        'teachers',
                        'reviews_count',
                        'average_rating',
                        'price_data',
                    ]
                ],
                'digital_assets' => [
                    '*' => [
                        'slug',
                        'name',
                        'price',
                        'original_price',
                        'price_range',
                        'has_discount',
                        'discount_percent',
                        'is_free',
                        'is_featured',
                        'product_type',
                        'thumbnail_url',
                        'teachers',
                        'reviews_count',
                        'average_rating',
                        'price_data',
                    ]
                ],
            ]
        ]);
        $responseData = $response->json('data');

        expect($responseData['courses'])->toHaveCount(5)
            ->and($responseData['seminars'])->toHaveCount(5)
            ->and($responseData['digital_assets'])->toHaveCount(5);
        foreach (['courses', 'seminars', 'digital_assets'] as $type) {
            foreach ($responseData[$type] as $product) {
                expect($product['price'])->toBeInt()
                    ->and($product['original_price'])->toBeInt()
                    ->and($product['price_range'])->toBeArray()
                    ->and($product['has_discount'])->toBeBool()
                    ->and($product['is_free'])->toBeBool()
                    ->and($product['is_featured'])->toBeBool()
                    ->and($product['product_type'])->toBeArray()
                    ->and($product['thumbnail_url'])->toBeString()
                    ->and($product['teachers'])->toBeArray()
                    ->and($product['reviews_count'])->toBeInt()
                    ->and($product['average_rating'])->toBeFloat()
                    ->and($product['price_data'])->toBeArray();
            }
        }
    });

    it('get single category details for courses', function () {
        $category = \App\Models\Category::factory()->create();

        $courses = Product::factory()
            ->withCourse()
            ->withDeliveryOptions(1)
            ->count(15)
            ->create();
        $courses->load('productDeliveryOptions', 'term', 'vendor');

        $category->products()->attach($courses);

        $response = $this->getJson(route('api.v1.shop.categories.courses',
            ['category' => $category->slug, 'per_page' => 10]));
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'data'  => [
                    '*' => [
                        'slug',
                        'name',
                        'price',
                        'original_price',
                        'price_range',
                        'has_discount',
                        'discount_percent',
                        'is_free',
                        'is_featured',
                        'product_type',
                        'thumbnail_url',
                        'teachers',
                        'reviews_count',
                        'average_rating',
                        'price_data',
                    ]
                ]
            ]
        ]);
        $responseData = $response->json('data');

        expect($responseData['data'])->toHaveCount(10)
            ->and($responseData['total'])->toBe(15)
            ->and($responseData['per_page'])->toBe(10)
            ->and($responseData['current_page'])->toBe(1)
            ->and($responseData['last_page'])->toBe(2);

        foreach ($responseData['data'] as $product) {
            expect($product['price'])->toBeInt()
                ->and($product['original_price'])->toBeInt()
                ->and($product['price_range'])->toBeArray()
                ->and($product['has_discount'])->toBeBool()
                ->and($product['is_free'])->toBeBool()
                ->and($product['is_featured'])->toBeBool()
                ->and($product['product_type'])->toBeArray()
                ->and($product['thumbnail_url'])->toBeString()
                ->and($product['teachers'])->toBeArray()
                ->and($product['reviews_count'])->toBeInt()
                ->and($product['average_rating'])->toBeFloat()
                ->and($product['price_data'])->toBeArray();
        }
    });

    it('get single category details for semianrs', function () {
        $category = \App\Models\Category::factory()->create();

        $seminars = Product::factory()
            ->withSeminar()
            ->withDeliveryOptions(1)
            ->count(15)
            ->create();


        $category->products()->attach($seminars);

        $response = $this->getJson(route('api.v1.shop.categories.seminars',
            ['category' => $category->slug, 'per_page' => 10]));
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'current_page',
                'data'  => [
                    '*' => [
                        'slug',
                        'name',
                        'price',
                        'original_price',
                        'price_range',
                        'has_discount',
                        'discount_percent',
                        'is_free',
                        'is_featured',
                        'product_type',
                        'thumbnail_url',
                        'teachers',
                        'reviews_count',
                        'average_rating',
                        'price_data',
                    ]
                ],
            ]
        ]);
        $responseData = $response->json('data');

        expect($responseData['data'])->toHaveCount(10)
            ->and($responseData['total'])->toBe(15)
            ->and($responseData['per_page'])->toBe(10)
            ->and($responseData['current_page'])->toBe(1)
            ->and($responseData['last_page'])->toBe(2);

        foreach ($responseData['data'] as $product) {
            expect($product['price'])->toBeInt()
                ->and($product['original_price'])->toBeInt()
                ->and($product['price_range'])->toBeArray()
                ->and($product['has_discount'])->toBeBool()
                ->and($product['is_free'])->toBeBool()
                ->and($product['is_featured'])->toBeBool()
                ->and($product['product_type'])->toBeArray()
                ->and($product['thumbnail_url'])->toBeString()
                ->and($product['teachers'])->toBeArray()
                ->and($product['reviews_count'])->toBeInt()
                ->and($product['average_rating'])->toBeFloat()
                ->and($product['price_data'])->toBeArray();
        }
    });
    it('get single category details for digital assets', function () {
        $category = \App\Models\Category::factory()->create();
        $assets = Product::factory()
            ->withDigitalAsset()
            ->withDeliveryOptions(1)
            ->count(15)
            ->create();
        $assets->load('productDeliveryOptions', 'term', 'vendor');
        $category->products()->attach($assets);
        $response = $this->getJson(route('api.v1.shop.categories.digital-assets',
            ['category' => $category->slug, 'per_page' => 10]));
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'current_page',
                'data' => [
                    '*' => [
                        'slug',
                        'name',
                        'price',
                        'original_price',
                        'price_range',
                        'has_discount',
                        'discount_percent',
                        'is_free',
                        'is_featured',
                        'product_type',
                        'thumbnail_url',
                        'teachers',
                        'reviews_count',
                        'average_rating',
                        'price_data',
                    ],
                ],
            ]
        ]);
        $responseData = $response->json('data');
        expect($responseData['data'])->toHaveCount(10)
            ->and($responseData['total'])->toBe(15)
            ->and($responseData['per_page'])->toBe(10)
            ->and($responseData['current_page'])->toBe(1)
            ->and($responseData['last_page'])->toBe(2);
        foreach ($responseData['data'] as $product) {
            expect($product['price'])->toBeInt()
                ->and($product['original_price'])->toBeInt()
                ->and($product['price_range'])->toBeArray()
                ->and($product['has_discount'])->toBeBool()
                ->and($product['is_free'])->toBeBool()
                ->and($product['is_featured'])->toBeBool()
                ->and($product['product_type'])->toBeArray()
                ->and($product['thumbnail_url'])->toBeString()
                ->and($product['teachers'])->toBeArray()
                ->and($product['reviews_count'])->toBeInt()
                ->and($product['average_rating'])->toBeFloat()
                ->and($product['price_data'])->toBeArray();
        }
    });
});
