<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Models\Category;
use App\Models\Course;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\ProductDeliveryOptionDiscountPrice;
use Hekmatinasser\Verta\Verta;
use Tests\Support\Traits\ProductTestTrait;

describe('Course API', function (): void {
    uses(ProductTestTrait::class);
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
                    'short_name',
                    'price_data',
                    'description',
                    'duration',
                    'difficulty_level',
                    'career_prospects_text',
                    'curriculum_summary_text',
                    'outcomes_json',
                    'default_teacher_info',
                    'provides_certificate',
                    'faq' => [
                        '*' => [
                            'question',
                            'answer',
                        ],
                    ],
                    'additional_info',
                    'meta_title',
                    'meta_description',
                    'meta_keywords',
                    'properties',
                    'details',
                    'status',
                    'categories' => [
                        '*' => [
                            'name',
                            'slug',
                            'icon_url',
                            'image_url',
                            'products_count',
                        ],
                    ],
                    'delivery_options' => [
                        '*' => [
                            'uuid',
                            'sku',
                            'name',
                            'price_data',
                            'fulfillment_type',
                            'delivery_method',
                        ],
                    ],
                    'media',
                ],
            ]);

        $resposneData = $response->json('data');
        expect($resposneData['slug'])->toBe($product->slug)
            ->and($resposneData['full_name'])->toBe($product->name)
            ->and(count($resposneData['delivery_options']))->toBe(2)
            ->and(collect($resposneData['delivery_options'])->pluck('price_data.current_price')->sort()->values()->all())
            ->toBe([1000000, 3000000]);
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
        $response = $this->getJson(route('api.v1.shop.courses.index', ['q' => 'Laravel']));
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
        $response = $this->getJson(route('api.v1.shop.courses.index',
            ['filter' => ['category_slugs' => ['programming']]]));
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
        $response = $this->getJson(route('api.v1.shop.courses.index',
            ['filter' => ['difficulty_level' => 'beginner']]));
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['name' => $product1->name])
            ->assertJsonMissing(['name' => $product2->name])
            ->assertJsonMissing(['name' => $product3->name]);
    });

    it('filter by price range', function (): void {
        $cheapCourse = Product::factory()
            ->withDeliveryOptions(1)
            ->withCourse(Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]))
            ->create(['name' => 'Affordable Course']);
        ProductDeliveryOption::where('product_id', $cheapCourse->id)->update([
            'price'            => 50_000,
            'status'           => PublicationStatusEnum::PUBLISHED->value,
            'fulfillment_type' => FulfillmentTypeEnum::DIGITAL->value,
        ]);
        $this->indexProductPrice($cheapCourse);

        $expensiveCourse = Product::factory()
            ->withDeliveryOptions(1)
            ->withCourse(Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]))
            ->create(['name' => 'Premium Course']);
        ProductDeliveryOption::where('product_id', $expensiveCourse->id)->update([
            'price'            => 300_000,
            'status'           => PublicationStatusEnum::PUBLISHED->value,
            'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
        ]);
        $this->indexProductPrice($expensiveCourse);

        $response = $this->getJson(route('api.v1.shop.courses.index', [
            'filter' => [
                'min_price' => '100000',
                'max_price' => '400000',
            ],
        ]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['name' => 'Premium Course'])
            ->assertJsonMissing(['name' => 'Affordable Course']);
    });

    it('filter by fulfillment type', function (): void {
        $onlineProduct = Product::factory()
            ->withDeliveryOptions(1)
            ->withCourse(Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]))
            ->create(['name' => 'Online Course']);
        ProductDeliveryOption::where('product_id', $onlineProduct->id)->update([
            'price'            => 150_000,
            'status'           => PublicationStatusEnum::PUBLISHED->value,
            'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
        ]);
        $this->indexProductPrice($onlineProduct);

        $digitalProduct = Product::factory()
            ->withDeliveryOptions(1)
            ->withCourse(Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]))
            ->create(['name' => 'Digital Course']);
        ProductDeliveryOption::where('product_id', $digitalProduct->id)->update([
            'price'            => 100_000,
            'status'           => PublicationStatusEnum::PUBLISHED->value,
            'fulfillment_type' => FulfillmentTypeEnum::DIGITAL->value,
        ]);
        $this->indexProductPrice($digitalProduct);

        $response = $this->getJson(route('api.v1.shop.courses.index', [
            'filter' => [
                'fulfillment_types' => ['digital'],
            ],
        ]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['name' => 'Digital Course'])
            ->assertJsonMissing(['name' => 'Online Course']);
    });

    it('filter by discounted products only', function (): void {
        $discountedCourse = Product::factory()
            ->withDeliveryOptions(1)
            ->withCourse(Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]))
            ->create(['name' => 'Discounted Course']);
        $discountedOption = ProductDeliveryOption::where('product_id', $discountedCourse->id)->first();
        $discountedOption->update([
            'price'            => 150_000,
            'status'           => PublicationStatusEnum::PUBLISHED->value,
            'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
        ]);
        ProductDeliveryOptionDiscountPrice::factory()
            ->forProductDeliveryOption($discountedOption)
            ->withPrice(120_000)
            ->create();
        $this->indexProductPrice($discountedCourse);

        $regularCourse = Product::factory()
            ->withDeliveryOptions(1)
            ->withCourse(Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]))
            ->create(['name' => 'Regular Course']);
        ProductDeliveryOption::where('product_id', $regularCourse->id)->update([
            'price'            => 100_000,
            'status'           => PublicationStatusEnum::PUBLISHED->value,
            'fulfillment_type' => FulfillmentTypeEnum::DIGITAL->value,
        ]);
        $this->indexProductPrice($regularCourse);

        $response = $this->getJson(route('api.v1.shop.courses.index', [
            'filter' => [
                'with_discounts' => 1,
            ],
        ]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['name' => 'Discounted Course'])
            ->assertJsonMissing(['name' => 'Regular Course']);
    });

    it('applies search, category, difficulty_level and fulfillment_type filters together', function (): void {
        $targetCategory = Category::factory()->create(['name' => 'Programming', 'slug' => 'programming']);
        $otherCategory  = Category::factory()->create(['name' => 'Design', 'slug' => 'design']);

        $targetCourse = Product::factory()
            ->withDeliveryOptions(1)
            ->withCourse(Course::factory()->create([
                'full_name'        => 'Laravel Zero to Hero',
                'short_name'       => 'Laravel Bootcamp',
                'difficulty_level' => CourseDifficultyLevelEnum::BEGINNER,
                'status'           => PublicationStatusEnum::PUBLISHED,
            ]))
            ->create(['name' => 'Laravel Bootcamp Special']);
        $targetCourse->categories()->attach($targetCategory->id);
        ProductDeliveryOption::where('product_id', $targetCourse->id)->update([
            'price'            => 150_000,
            'status'           => PublicationStatusEnum::PUBLISHED->value,
            'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
        ]);
        $this->indexProductPrice($targetCourse);

        $otherCourse = Product::factory()
            ->withDeliveryOptions(1)
            ->withCourse(Course::factory()->create([
                'full_name'        => 'Symfony From Scratch',
                'short_name'       => 'Symfony Mastery',
                'difficulty_level' => CourseDifficultyLevelEnum::ADVANCED,
                'status'           => PublicationStatusEnum::PUBLISHED,
            ]))
            ->create(['name' => 'Symfony Mastery']);
        $otherCourse->categories()->attach($otherCategory->id);
        ProductDeliveryOption::where('product_id', $otherCourse->id)->update([
            'price'            => 200_000,
            'status'           => PublicationStatusEnum::PUBLISHED->value,
            'fulfillment_type' => FulfillmentTypeEnum::DIGITAL->value,
        ]);
        $this->indexProductPrice($otherCourse);

        $response = $this->getJson(route('api.v1.shop.courses.index', [
            'q'      => 'Laravel',
            'filter' => [
                'category_slugs'    => ['programming'],
                'difficulty_level'  => 'beginner',
                'fulfillment_types' => ['online_service'],
            ],
        ]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['name' => 'Laravel Bootcamp Special'])
            ->assertJsonMissing(['name' => 'Symfony Mastery']);
    });

    it('filter by availability - available now', function (): void {
        $now = now();

        // Available course with current registration and content windows
        $availableCourse = Product::factory()
            ->withDeliveryOptions(1)
            ->withCourse(Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]))
            ->create(['name' => 'Available Now Course']);
        ProductDeliveryOption::where('product_id', $availableCourse->id)->update([
            'price'                   => 100_000,
            'status'                  => PublicationStatusEnum::PUBLISHED->value,
            'fulfillment_type'        => FulfillmentTypeEnum::DIGITAL->value,
            'registration_start_date' => $now->clone()->subDays(5)->toDateString(),
            'registration_end_date'   => $now->clone()->addDays(5)->toDateString(),
            'available_from'          => $now->clone()->subDays(2)->toDateString(),
            'available_to'            => $now->clone()->addDays(10)->toDateString(),
        ]);
        $this->indexProductPrice($availableCourse);

        // Not available - registration ended
        $pastCourse = Product::factory()
            ->withDeliveryOptions(1)
            ->withCourse(Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]))
            ->create(['name' => 'Past Registration Course']);
        ProductDeliveryOption::where('product_id', $pastCourse->id)->update([
            'price'                   => 100_000,
            'status'                  => PublicationStatusEnum::PUBLISHED->value,
            'fulfillment_type'        => FulfillmentTypeEnum::DIGITAL->value,
            'registration_start_date' => $now->clone()->subDays(20)->toDateString(),
            'registration_end_date'   => $now->clone()->subDays(5)->toDateString(),
            'available_from'          => $now->clone()->subDays(2)->toDateString(),
            'available_to'            => $now->clone()->addDays(10)->toDateString(),
        ]);
        $this->indexProductPrice($pastCourse);

        $response = $this->getJson(route('api.v1.shop.courses.index', [
            'filter' => [
                'is_available_now' => 1,
            ],
        ]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['name' => 'Available Now Course'])
            ->assertJsonMissing(['name' => 'Past Registration Course']);
    });

    it('filter by registration start date', function (): void {
        $now = now();
        // Convert to Jalali date for the filter
        $futureDate = Verta::instance($now->clone()->addDays(10))->format('Y-m-d');

        // Registration starts in the future
        $futureCourse = Product::factory()
            ->withDeliveryOptions(1)
            ->withCourse(Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]))
            ->create(['name' => 'Future Registration Course']);
        ProductDeliveryOption::where('product_id', $futureCourse->id)->update([
            'price'                   => 100_000,
            'status'                  => PublicationStatusEnum::PUBLISHED->value,
            'fulfillment_type'        => FulfillmentTypeEnum::DIGITAL->value,
            'registration_start_date' => $now->clone()->addDays(15)->toDateString(),
            'registration_end_date'   => $now->clone()->addDays(25)->toDateString(),
            'available_from'          => $now->clone()->addDays(30)->toDateString(),
            'available_to'            => $now->clone()->addDays(40)->toDateString(),
        ]);
        $this->indexProductPrice($futureCourse);

        // Registration started in the past
        $pastCourse = Product::factory()
            ->withDeliveryOptions(1)
            ->withCourse(Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]))
            ->create(['name' => 'Past Registration Start Course']);
        ProductDeliveryOption::where('product_id', $pastCourse->id)->update([
            'price'                   => 100_000,
            'status'                  => PublicationStatusEnum::PUBLISHED->value,
            'fulfillment_type'        => FulfillmentTypeEnum::DIGITAL->value,
            'registration_start_date' => $now->clone()->subDays(5)->toDateString(),
            'registration_end_date'   => $now->clone()->addDays(5)->toDateString(),
            'available_from'          => $now->clone()->addDays(10)->toDateString(),
            'available_to'            => $now->clone()->addDays(40)->toDateString(),
        ]);
        $this->indexProductPrice($pastCourse);

        $response = $this->getJson(route('api.v1.shop.courses.index', [
            'filter' => [
                'registration_starts_after' => $futureDate,
            ],
        ]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['name' => 'Future Registration Course'])
            ->assertJsonMissing(['name' => 'Past Registration Start Course']);
    });

    it('filter by content availability window', function (): void {
        $now = now();
        // Convert to Jalali dates for the filter
        $startDate = verta()->addDays(5)->format('Y-m-d');
        $endDate   = verta()->addDays(15)->format('Y-m-d');

        // Course that overlaps with the specified window
        $windowCourse = Product::factory()
            ->withDeliveryOptions(1)
            ->withCourse(Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]))
            ->create(['name' => 'Window Available Course']);
        ProductDeliveryOption::where('product_id', $windowCourse->id)
            ->update([
                'price'                   => 100_000,
                'status'                  => PublicationStatusEnum::PUBLISHED->value,
                'fulfillment_type'        => FulfillmentTypeEnum::DIGITAL->value,
                'registration_start_date' => $now->clone()->subDays(5)->toDateString(),
                'registration_end_date'   => $now->clone()->addDays(20)->toDateString(),
                'available_from'          => $now->clone()->addDays(3)->toDateString(),
                'available_to'            => $now->clone()->addDays(20)->toDateString(),
            ]);
        $this->indexProductPrice($windowCourse);

        // Course that does not overlap with the specified window
        $outsideWindowCourse = Product::factory()
            ->withDeliveryOptions(1)
            ->withCourse(Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]))
            ->create(['name' => 'Outside Window Course']);
        ProductDeliveryOption::where('product_id', $outsideWindowCourse->id)->update([
            'price'                   => 100_000,
            'status'                  => PublicationStatusEnum::PUBLISHED->value,
            'fulfillment_type'        => FulfillmentTypeEnum::DIGITAL->value,
            'registration_start_date' => $now->clone()->subDays(5)->toDateString(),
            'registration_end_date'   => $now->clone()->addDays(2)->toDateString(),
            'available_from'          => $now->clone()->subDays(10)->toDateString(),
            'available_to'            => $now->clone()->subDays(3)->toDateString(),
        ]);
        $response = $this->getJson(route('api.v1.shop.courses.index', [
            'filter' => [
                'available_from' => $startDate,
                'available_to'   => $endDate,
            ],
        ]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['name' => 'Window Available Course'])
            ->assertJsonMissing(['name' => 'Outside Window Course']);
    });

});
