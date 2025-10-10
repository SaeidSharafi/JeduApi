<?php

declare(strict_types=1);

use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Models\Category;
use App\Models\Course;
use App\Models\Product;

describe('Course API', function (): void {
    it('get a list of courses', function (): void {
        $product = Product::factory()
            ->withDeliveryOptions(1)
            ->count(5)
            ->withCourse()
            ->create();

        $response = $this->getJson(route('api.v1.shop.courses.index'));

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data.data')
            ->assertJsonStructure([
                'message',
                'data' => [
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
                ],
                'metadata',
            ]);
    });

    it('get a specific course by slug', function (): void {
        $product = Product::factory()
            ->withDeliveryOptions(realData: [
                [
                    'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE,
                    'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE,
                    'price'            => 1000000,
                ],
                [
                    'fulfillment_type' => FulfillmentTypeEnum::IN_PERSON_SERVICE,
                    'delivery_method'  => DeliveryMethodEnum::IN_PERSON,
                    'price'            => 3000000,
                ],
            ])
            ->withCourse()
            ->create();

        $response = $this->getJson(route('api.v1.shop.courses.show', ['product' => $product->slug]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'slug',
                    'full_name',
                    'description',
                    'categories',
                    'delivery_options',
                ],
            ]);

        $resposneData = $response->json('data');
        expect($resposneData['slug'])->toBe($product->slug)
            ->and($resposneData['full_name'])->toBe($product->name)
            ->and(count($resposneData['delivery_options']))->toBe(2)
            ->and($resposneData['delivery_options'][0]['price_data']['current_price'])->toBe(1000000)
            ->and($resposneData['delivery_options'][1]['price_data']['current_price'])->toBe(3000000);
    });
});
describe('Course Lsiting filters', function (): void {
    // it has seach value : filter by search in title and description
    it('filter by search in title and description', function (): void {
        Product::factory()
            ->withDeliveryOptions(1)
            ->withCourse(
                Course::factory()->create(
                    ['short_name' => 'Laravel for Beginners', 'description' => 'Learn Laravel from scratch']
                )
            )
            ->create(['name' => 'Laravel for Beginners']);
        Product::factory()
            ->withDeliveryOptions(1)
            ->withCourse(Course::factory()->create(
                ['short_name' => 'Advanced PHP', 'description' => 'Deep dive into PHP programming']
            ))
            ->create(['name' => 'Advanced PHP']);
        Product::factory()
            ->withDeliveryOptions(1)
            ->withCourse(Course::factory()->create(
                ['short_name' => 'JavaScript Basics', 'description' => 'Introduction to JavaScript']
            ))
            ->create(['name' => 'JavaScript Basics']);
        $response = $this->getJson(route('api.v1.shop.courses.index', ['search' => 'Laravel']));
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['name' => 'Laravel for Beginners']);
    });
    // it has category slug : filter by category
    it('filter by category', function (): void {
        $category          = Category::factory()->create(['name' => 'Programming', 'slug' => 'programming']);
        $otherCategory     = Category::factory()->create(['name' => 'Design', 'slug' => 'design']);
        $productInCategory = Product::factory()
            ->withDeliveryOptions(1)
            ->withCourse()
            ->create();
        $productInCategory->categories()->attach($category->id);
        $productNotInCategory = Product::factory()
            ->withDeliveryOptions(1)
            ->withCourse()
            ->create();
        $productNotInCategory->categories()->attach($otherCategory->id);
        $response = $this->getJson(route('api.v1.shop.courses.index', ['filter[category_slugs][]' => 'programming']));
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['slug' => $productInCategory->slug])
            ->assertJsonMissing(['slug' => $productNotInCategory->slug]);

    });
    it('filter by difficulty_level', function (): void {
        $product1 = Product::factory()
            ->withDeliveryOptions(1)
            ->withCourse(Course::factory()->create(['difficulty_level' => CourseDifficultyLevelEnum::BEGINNER]))
            ->create();
        $product2 = Product::factory()
            ->withDeliveryOptions(1)
            ->withCourse(Course::factory()->create(['difficulty_level' => CourseDifficultyLevelEnum::INTERMEDIATE]))
            ->create();
        $product3 = Product::factory()
            ->withDeliveryOptions(1)
            ->withCourse(Course::factory()->create(['difficulty_level' => CourseDifficultyLevelEnum::ADVANCED]))
            ->create();
        $response = $this->getJson(route('api.v1.shop.courses.index', ['filter[difficulty_level]' => 'beginner']));
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['name' => $product1->name])
            ->assertJsonMissing(['name' => $product2->name])
            ->assertJsonMissing(['name' => $product3->name]);
    });
});
