<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Models\Category;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\ProductDeliveryOptionDiscountPrice;
use App\Models\Seminar;
use Hekmatinasser\Verta\Verta;
use Tests\Support\Traits\ProductTestTrait;

describe('Product Listing API - :dataset', function (): void {
    uses(ProductTestTrait::class);
    it('get a list of products', function (string $factoryMethod, string $routePrefix): void {
        $product = Product::factory()
            ->withDeliveryOptions(1)
            ->count(5)
            ->$factoryMethod()
            ->create();

        $response = $this->getJson(route("api.v1.shop.{$routePrefix}.index"));

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
    })->with('product_types');
})->group('product-listing');

describe('Product Listing Filters - :dataset', function (): void {
    it('filter by search in title and description', function (string $factoryMethod, string $routePrefix): void {
        $productableFactory = match ($factoryMethod) {
            'withCourse' => Course::factory()->create([
                'short_name'  => 'Laravel for Beginners',
                'description' => 'Learn Laravel from scratch',
            ]),
            'withSeminar' => Seminar::factory()->create([
                'short_name'  => 'Laravel for Beginners',
                'description' => 'Learn Laravel from scratch',
            ]),
            'withDigitalAsset' => DigitalAsset::factory()->create([
                'short_name'  => 'Laravel for Beginners',
                'description' => 'Learn Laravel from scratch',
            ])
        };
        Product::factory()->withDeliveryOptions(1)->$factoryMethod($productableFactory)->create(['name' => 'Laravel for Beginners']);

        $productableFactory = match ($factoryMethod) {
            'withCourse' => Course::factory()->create([
                'short_name'  => 'Advanced PHP',
                'description' => 'Deep dive into PHP programming',
            ]),
            'withSeminar' => Seminar::factory()->create([
                'short_name'  => 'Advanced PHP',
                'description' => 'Deep dive into PHP programming',
            ]),
            'withDigitalAsset' => DigitalAsset::factory()->create([
                'short_name'  => 'Advanced PHP',
                'description' => 'Deep dive into PHP programming',
            ])
        };
        Product::factory()->withDeliveryOptions(1)->$factoryMethod($productableFactory)->create(['name' => 'Advanced PHP']);

        $productableFactory = match ($factoryMethod) {
            'withCourse' => Course::factory()->create([
                'short_name'  => 'JavaScript Basics',
                'description' => 'Introduction to JavaScript',
            ]),
            'withSeminar' => Seminar::factory()->create([
                'short_name'  => 'JavaScript Basics',
                'description' => 'Introduction to JavaScript',
            ]),
            'withDigitalAsset' => DigitalAsset::factory()->create([
                'short_name'  => 'JavaScript Basics',
                'description' => 'Introduction to JavaScript',
            ])
        };
        Product::factory()->withDeliveryOptions(1)->$factoryMethod($productableFactory)->create(['name' => 'JavaScript Basics']);

        $response = $this->getJson(route("api.v1.shop.{$routePrefix}.index", ['q' => 'Laravel']));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['name' => 'Laravel for Beginners']);
    })->with('product_types');

    it('filter by category', function (string $factoryMethod, string $routePrefix): void {
        $category      = Category::factory()->create(['name' => 'Programming', 'slug' => 'programming']);
        $otherCategory = Category::factory()->create(['name' => 'Design', 'slug' => 'design']);

        $productInCategory = Product::factory()
            ->withDeliveryOptions(1)
            ->$factoryMethod()
            ->create();
        $productInCategory->categories()->attach($category->id);

        $productNotInCategory = Product::factory()
            ->withDeliveryOptions(1)
            ->$factoryMethod()
            ->create();
        $productNotInCategory->categories()->attach($otherCategory->id);

        $response = $this->getJson(route("api.v1.shop.{$routePrefix}.index", [
            'filter' => ['category_slugs' => ['programming']],
        ]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['slug' => $productInCategory->slug])
            ->assertJsonMissing(['slug' => $productNotInCategory->slug]);
    })->with('product_types');

    it('filter by difficulty_level', function (string $factoryMethod, string $routePrefix): void {
        $product1 = Product::factory()
            ->withDeliveryOptions(1)
            ->$factoryMethod(
                match ($factoryMethod) {
                    'withCourse' => Course::factory()->create([
                        'difficulty_level' => CourseDifficultyLevelEnum::BEGINNER,
                    ]),
                    'withSeminar' => Seminar::factory()->create([
                        'difficulty_level' => CourseDifficultyLevelEnum::BEGINNER,
                    ]),
                    'withDigitalAsset' => DigitalAsset::factory()->create([
                        'difficulty_level' => CourseDifficultyLevelEnum::BEGINNER,
                    ]),
                }
            )
            ->create();

        $product2 = Product::factory()
            ->withDeliveryOptions(1)
            ->$factoryMethod(
                match ($factoryMethod) {
                    'withCourse' => Course::factory()->create([
                        'difficulty_level' => CourseDifficultyLevelEnum::INTERMEDIATE,
                    ]),
                    'withSeminar' => Seminar::factory()->create([
                        'difficulty_level' => CourseDifficultyLevelEnum::INTERMEDIATE,
                    ]),
                    'withDigitalAsset' => DigitalAsset::factory()->create([
                        'difficulty_level' => CourseDifficultyLevelEnum::INTERMEDIATE,
                    ]),
                }
            )
            ->create();

        $product3 = Product::factory()
            ->withDeliveryOptions(1)
            ->$factoryMethod(
                match ($factoryMethod) {
                    'withCourse' => Course::factory()->create([
                        'difficulty_level' => CourseDifficultyLevelEnum::ADVANCED,
                    ]),
                    'withSeminar' => Seminar::factory()->create([
                        'difficulty_level' => CourseDifficultyLevelEnum::ADVANCED,
                    ]),
                    'withDigitalAsset' => DigitalAsset::factory()->create([
                        'difficulty_level' => CourseDifficultyLevelEnum::ADVANCED,
                    ]),
                }
            )
            ->create();

        $response = $this->getJson(route("api.v1.shop.{$routePrefix}.index", [
            'filter' => ['difficulty_level' => 'beginner'],
        ]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['name' => $product1->name])
            ->assertJsonMissing(['name' => $product2->name])
            ->assertJsonMissing(['name' => $product3->name]);
    })->with('product_types');

    it('filter by price range', function (string $factoryMethod, string $routePrefix): void {
        $cheapProduct = Product::factory()
            ->withDeliveryOptions(1)
            ->$factoryMethod(
                match ($factoryMethod) {
                    'withCourse'       => Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withSeminar'      => Seminar::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withDigitalAsset' => DigitalAsset::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                }
            )
            ->create(['name' => 'Affordable Product']);
        ProductDeliveryOption::where('product_id', $cheapProduct->id)->update([
            'price'            => 50_000,
            'status'           => PublicationStatusEnum::PUBLISHED->value,
            'fulfillment_type' => FulfillmentTypeEnum::DIGITAL->value,
        ]);
        $this->indexProductPrice($cheapProduct);

        $expensiveProduct = Product::factory()
            ->withDeliveryOptions(1)
            ->$factoryMethod(
                match ($factoryMethod) {
                    'withCourse'       => Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withSeminar'      => Seminar::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withDigitalAsset' => DigitalAsset::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                }
            )
            ->create(['name' => 'Premium Product']);
        ProductDeliveryOption::where('product_id', $expensiveProduct->id)->update([
            'price'            => 300_000,
            'status'           => PublicationStatusEnum::PUBLISHED->value,
            'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
        ]);
        $this->indexProductPrice($expensiveProduct);

        $response = $this->getJson(route("api.v1.shop.{$routePrefix}.index", [
            'filter' => [
                'min_price' => '100000',
                'max_price' => '400000',
            ],
        ]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['name' => 'Premium Product'])
            ->assertJsonMissing(['name' => 'Affordable Product']);
    })->with('product_types');

    it('filter by fulfillment type', function (string $factoryMethod, string $routePrefix): void {
        $onlineProduct = Product::factory()
            ->withDeliveryOptions(1)
            ->$factoryMethod(
                match ($factoryMethod) {
                    'withCourse'       => Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withSeminar'      => Seminar::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withDigitalAsset' => DigitalAsset::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                }
            )
            ->create(['name' => 'Online Product']);
        ProductDeliveryOption::where('product_id', $onlineProduct->id)->update([
            'price'            => 150_000,
            'status'           => PublicationStatusEnum::PUBLISHED->value,
            'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
        ]);
        $this->indexProductPrice($onlineProduct);

        $digitalProduct = Product::factory()
            ->withDeliveryOptions(1)
            ->$factoryMethod(
                match ($factoryMethod) {
                    'withCourse'       => Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withSeminar'      => Seminar::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withDigitalAsset' => DigitalAsset::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                }
            )
            ->create(['name' => 'Digital Product']);
        ProductDeliveryOption::where('product_id', $digitalProduct->id)->update([
            'price'            => 100_000,
            'status'           => PublicationStatusEnum::PUBLISHED->value,
            'fulfillment_type' => FulfillmentTypeEnum::DIGITAL->value,
        ]);
        $this->indexProductPrice($digitalProduct);

        $response = $this->getJson(route("api.v1.shop.{$routePrefix}.index", [
            'filter' => [
                'fulfillment_types' => ['digital'],
            ],
        ]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['name' => 'Digital Product'])
            ->assertJsonMissing(['name' => 'Online Product']);
    })->with('product_types');

    it('filter by discounted products only', function (string $factoryMethod, string $routePrefix): void {
        $discountedProduct = Product::factory()
            ->withDeliveryOptions(1)
            ->$factoryMethod(
                match ($factoryMethod) {
                    'withCourse'       => Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withSeminar'      => Seminar::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withDigitalAsset' => DigitalAsset::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                }
            )
            ->create(['name' => 'Discounted Product']);
        $discountedOption = ProductDeliveryOption::where('product_id', $discountedProduct->id)->first();
        $discountedOption->update([
            'price'            => 150_000,
            'status'           => PublicationStatusEnum::PUBLISHED->value,
            'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
        ]);
        ProductDeliveryOptionDiscountPrice::factory()
            ->forProductDeliveryOption($discountedOption)
            ->withPrice(120_000)
            ->create();
        $this->indexProductPrice($discountedProduct);

        $regularProduct = Product::factory()
            ->withDeliveryOptions(1)
            ->$factoryMethod(
                match ($factoryMethod) {
                    'withCourse'       => Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withSeminar'      => Seminar::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withDigitalAsset' => DigitalAsset::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                }
            )
            ->create(['name' => 'Regular Product']);
        ProductDeliveryOption::where('product_id', $regularProduct->id)->update([
            'price'            => 100_000,
            'status'           => PublicationStatusEnum::PUBLISHED->value,
            'fulfillment_type' => FulfillmentTypeEnum::DIGITAL->value,
        ]);
        $this->indexProductPrice($regularProduct);

        $response = $this->getJson(route("api.v1.shop.{$routePrefix}.index", [
            'filter' => [
                'with_discounts' => 1,
            ],
        ]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['name' => 'Discounted Product'])
            ->assertJsonMissing(['name' => 'Regular Product']);
    })->with('product_types');

    it('applies search, category, difficulty_level and fulfillment_type filters together', function (string $factoryMethod, string $routePrefix): void {
        $targetCategory = Category::factory()->create(['name' => 'Programming', 'slug' => 'programming']);
        $otherCategory  = Category::factory()->create(['name' => 'Design', 'slug' => 'design']);

        $targetProduct = Product::factory()
            ->withDeliveryOptions(1)
            ->$factoryMethod(
                match ($factoryMethod) {
                    'withCourse' => Course::factory()->create([
                        'full_name'        => 'Laravel Zero to Hero',
                        'short_name'       => 'Laravel Bootcamp',
                        'difficulty_level' => CourseDifficultyLevelEnum::BEGINNER,
                        'status'           => PublicationStatusEnum::PUBLISHED,
                    ]),
                    'withSeminar' => Seminar::factory()->create([
                        'short_name'       => 'Laravel Bootcamp',
                        'difficulty_level' => CourseDifficultyLevelEnum::BEGINNER,
                        'status'           => PublicationStatusEnum::PUBLISHED,
                    ]),
                    'withDigitalAsset' => DigitalAsset::factory()->create([
                        'short_name'       => 'Laravel Bootcamp',
                        'difficulty_level' => CourseDifficultyLevelEnum::BEGINNER,
                        'status'           => PublicationStatusEnum::PUBLISHED,
                    ]),
                }
            )
            ->create(['name' => 'Laravel Bootcamp Special']);
        $targetProduct->categories()->attach($targetCategory->id);
        ProductDeliveryOption::where('product_id', $targetProduct->id)->update([
            'price'            => 150_000,
            'status'           => PublicationStatusEnum::PUBLISHED->value,
            'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
        ]);
        $this->indexProductPrice($targetProduct);

        $otherProduct = Product::factory()
            ->withDeliveryOptions(1)
            ->$factoryMethod(
                match ($factoryMethod) {
                    'withCourse' => Course::factory()->create([
                        'full_name'        => 'Symfony From Scratch',
                        'short_name'       => 'Symfony Mastery',
                        'difficulty_level' => CourseDifficultyLevelEnum::ADVANCED,
                        'status'           => PublicationStatusEnum::PUBLISHED,
                    ]),
                    'withSeminar' => Seminar::factory()->create([
                        'short_name'       => 'Symfony Mastery',
                        'difficulty_level' => CourseDifficultyLevelEnum::ADVANCED,
                        'status'           => PublicationStatusEnum::PUBLISHED,
                    ]),
                    'withDigitalAsset' => DigitalAsset::factory()->create([
                        'short_name'       => 'Symfony Mastery',
                        'difficulty_level' => CourseDifficultyLevelEnum::ADVANCED,
                        'status'           => PublicationStatusEnum::PUBLISHED,
                    ]),
                }
            )
            ->create(['name' => 'Symfony Mastery']);
        $otherProduct->categories()->attach($otherCategory->id);
        ProductDeliveryOption::where('product_id', $otherProduct->id)->update([
            'price'            => 200_000,
            'status'           => PublicationStatusEnum::PUBLISHED->value,
            'fulfillment_type' => FulfillmentTypeEnum::DIGITAL->value,
        ]);
        $this->indexProductPrice($otherProduct);

        $response = $this->getJson(route("api.v1.shop.{$routePrefix}.index", [
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
    })->with('product_types');

    it('filter by availability - available now', function (string $factoryMethod, string $routePrefix): void {
        $now = now();

        // Available product with current registration and content windows
        $availableProduct = Product::factory()
            ->withDeliveryOptions(1)
            ->$factoryMethod(
                match ($factoryMethod) {
                    'withCourse'       => Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withSeminar'      => Seminar::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withDigitalAsset' => DigitalAsset::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                }
            )
            ->create(['name' => 'Available Now Product']);
        ProductDeliveryOption::where('product_id', $availableProduct->id)->update([
            'price'                   => 100_000,
            'status'                  => PublicationStatusEnum::PUBLISHED->value,
            'fulfillment_type'        => FulfillmentTypeEnum::DIGITAL->value,
            'registration_start_date' => $now->clone()->subDays(5)->toDateString(),
            'registration_end_date'   => $now->clone()->addDays(5)->toDateString(),
            'available_from'          => $now->clone()->subDays(2)->toDateString(),
            'available_to'            => $now->clone()->addDays(10)->toDateString(),
        ]);
        $this->indexProductPrice($availableProduct);

        // Not available - registration ended
        $pastProduct = Product::factory()
            ->withDeliveryOptions(1)
            ->$factoryMethod(
                match ($factoryMethod) {
                    'withCourse'       => Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withSeminar'      => Seminar::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withDigitalAsset' => DigitalAsset::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                }
            )
            ->create(['name' => 'Past Registration Product']);
        ProductDeliveryOption::where('product_id', $pastProduct->id)->update([
            'price'                   => 100_000,
            'status'                  => PublicationStatusEnum::PUBLISHED->value,
            'fulfillment_type'        => FulfillmentTypeEnum::DIGITAL->value,
            'registration_start_date' => $now->clone()->subDays(20)->toDateString(),
            'registration_end_date'   => $now->clone()->subDays(5)->toDateString(),
            'available_from'          => $now->clone()->subDays(2)->toDateString(),
            'available_to'            => $now->clone()->addDays(10)->toDateString(),
        ]);
        $this->indexProductPrice($pastProduct);

        $response = $this->getJson(route("api.v1.shop.{$routePrefix}.index", [
            'filter' => [
                'is_available_now' => 1,
            ],
        ]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['name' => 'Available Now Product'])
            ->assertJsonMissing(['name' => 'Past Registration Product']);
    })->with('product_types');

    it('filter by registration start date', function (string $factoryMethod, string $routePrefix): void {
        $now        = now();
        $futureDate = Verta::instance($now->clone()->addDays(10))->format('Y-m-d');

        // Registration starts in the future
        $futureProduct = Product::factory()
            ->withDeliveryOptions(1)
            ->$factoryMethod(
                match ($factoryMethod) {
                    'withCourse'       => Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withSeminar'      => Seminar::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withDigitalAsset' => DigitalAsset::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                }
            )
            ->create(['name' => 'Future Registration Product']);
        ProductDeliveryOption::where('product_id', $futureProduct->id)->update([
            'price'                   => 100_000,
            'status'                  => PublicationStatusEnum::PUBLISHED->value,
            'fulfillment_type'        => FulfillmentTypeEnum::DIGITAL->value,
            'registration_start_date' => $now->clone()->addDays(15)->toDateString(),
            'registration_end_date'   => $now->clone()->addDays(25)->toDateString(),
            'available_from'          => $now->clone()->subDays(5)->toDateString(),
            'available_to'            => $now->clone()->addDays(40)->toDateString(),
        ]);
        $this->indexProductPrice($futureProduct);

        // Registration started in the past
        $pastProduct = Product::factory()
            ->withDeliveryOptions(1)
            ->$factoryMethod(
                match ($factoryMethod) {
                    'withCourse'       => Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withSeminar'      => Seminar::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withDigitalAsset' => DigitalAsset::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                }
            )
            ->create(['name' => 'Past Registration Start Product']);
        ProductDeliveryOption::where('product_id', $pastProduct->id)->update([
            'price'                   => 100_000,
            'status'                  => PublicationStatusEnum::PUBLISHED->value,
            'fulfillment_type'        => FulfillmentTypeEnum::DIGITAL->value,
            'registration_start_date' => $now->clone()->subDays(5)->toDateString(),
            'registration_end_date'   => $now->clone()->addDays(5)->toDateString(),
            'available_from'          => $now->clone()->subDays(10)->toDateString(),
            'available_to'            => $now->clone()->addDays(40)->toDateString(),
        ]);
        $this->indexProductPrice($pastProduct);

        $response = $this->getJson(route("api.v1.shop.{$routePrefix}.index", [
            'filter' => [
                'registration_starts_after' => $futureDate,
            ],
        ]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['name' => 'Future Registration Product'])
            ->assertJsonMissing(['name' => 'Past Registration Start Product']);
    })->with('product_types');

    it('filter by content availability window', function (string $factoryMethod, string $routePrefix): void {
        $now       = now();
        $startDate = verta()->addDays(5)->format('Y-m-d');
        $endDate   = verta()->addDays(15)->format('Y-m-d');

        // Product that overlaps with the specified window
        $windowProduct = Product::factory()
            ->withDeliveryOptions(1)
            ->$factoryMethod(
                match ($factoryMethod) {
                    'withCourse'       => Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withSeminar'      => Seminar::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withDigitalAsset' => DigitalAsset::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                }
            )
            ->create(['name' => 'Window Available Product']);
        ProductDeliveryOption::where('product_id', $windowProduct->id)
            ->update([
                'price'                   => 100_000,
                'status'                  => PublicationStatusEnum::PUBLISHED->value,
                'fulfillment_type'        => FulfillmentTypeEnum::DIGITAL->value,
                'registration_start_date' => $now->clone()->subDays(5)->toDateString(),
                'registration_end_date'   => $now->clone()->addDays(20)->toDateString(),
                'available_from'          => $now->clone()->subDays(2)->toDateString(),
                'available_to'            => $now->clone()->addDays(20)->toDateString(),
            ]);
        $this->indexProductPrice($windowProduct);

        // Product that does not overlap with the specified window
        $outsideWindowProduct = Product::factory()
            ->withDeliveryOptions(1)
            ->$factoryMethod(
                match ($factoryMethod) {
                    'withCourse'       => Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withSeminar'      => Seminar::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withDigitalAsset' => DigitalAsset::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                }
            )
            ->create(['name' => 'Outside Window Product']);
        ProductDeliveryOption::where('product_id', $outsideWindowProduct->id)->update([
            'price'                   => 100_000,
            'status'                  => PublicationStatusEnum::PUBLISHED->value,
            'fulfillment_type'        => FulfillmentTypeEnum::DIGITAL->value,
            'registration_start_date' => $now->clone()->subDays(5)->toDateString(),
            'registration_end_date'   => $now->clone()->addDays(2)->toDateString(),
            'available_from'          => $now->clone()->subDays(10)->toDateString(),
            'available_to'            => $now->clone()->subDays(3)->toDateString(),
        ]);

        $response = $this->getJson(route("api.v1.shop.{$routePrefix}.index", [
            'filter' => [
                'available_from' => $startDate,
                'available_to'   => $endDate,
            ],
        ]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['name' => 'Window Available Product'])
            ->assertJsonMissing(['name' => 'Outside Window Product']);
    })->with('product_types');

    it('sort by capacity utilization prioritizes near-capacity products', function (string $factoryMethod, string $routePrefix): void {
        $highUtilizationProduct = Product::factory()
            ->withDeliveryOptions(1)
            ->$factoryMethod(
                match ($factoryMethod) {
                    'withCourse'       => Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withSeminar'      => Seminar::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withDigitalAsset' => DigitalAsset::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                }
            )
            ->create(['name' => 'High Utilization Product']);
        ProductDeliveryOption::where('product_id', $highUtilizationProduct->id)->update([
            'status'         => PublicationStatusEnum::PUBLISHED->value,
            'capacity'       => 30,
            'enrolled_count' => 27,
        ]);

        $mediumUtilizationProduct = Product::factory()
            ->withDeliveryOptions(1)
            ->$factoryMethod(
                match ($factoryMethod) {
                    'withCourse'       => Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withSeminar'      => Seminar::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withDigitalAsset' => DigitalAsset::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                }
            )
            ->create(['name' => 'Medium Utilization Product']);
        ProductDeliveryOption::where('product_id', $mediumUtilizationProduct->id)->update([
            'status'         => PublicationStatusEnum::PUBLISHED->value,
            'capacity'       => 30,
            'enrolled_count' => 24,
        ]);

        $lowUtilizationProduct = Product::factory()
            ->withDeliveryOptions(1)
            ->$factoryMethod(
                match ($factoryMethod) {
                    'withCourse'       => Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withSeminar'      => Seminar::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withDigitalAsset' => DigitalAsset::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                }
            )
            ->create(['name' => 'Low Utilization Product']);
        ProductDeliveryOption::where('product_id', $lowUtilizationProduct->id)->update([
            'status'         => PublicationStatusEnum::PUBLISHED->value,
            'capacity'       => 30,
            'enrolled_count' => 6,
        ]);

        $response = $this->getJson(route("api.v1.shop.{$routePrefix}.index", [
            'sortBy'    => 'capacity_utilization',
            'sortOrder' => 'desc',
            'filter'    => [
                'capacity_threshold' => 0.8,
            ],
        ]));

        $response->assertStatus(200)
            ->assertJsonPath('data.data.0.slug', $highUtilizationProduct->slug)
            ->assertJsonPath('data.data.1.slug', $mediumUtilizationProduct->slug)
            ->assertJsonPath('data.data.2.slug', $lowUtilizationProduct->slug);
    })->with('product_types');

    it('filter by near capacity only', function (string $factoryMethod, string $routePrefix): void {
        $nearCapacityProduct = Product::factory()
            ->withDeliveryOptions(1)
            ->$factoryMethod(
                match ($factoryMethod) {
                    'withCourse'       => Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withSeminar'      => Seminar::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withDigitalAsset' => DigitalAsset::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                }
            )
            ->create(['name' => 'Near Capacity Product']);
        ProductDeliveryOption::where('product_id', $nearCapacityProduct->id)->update([
            'status'         => PublicationStatusEnum::PUBLISHED->value,
            'capacity'       => 30,
            'enrolled_count' => 25,
        ]);

        $belowThresholdProduct = Product::factory()
            ->withDeliveryOptions(1)
            ->$factoryMethod(
                match ($factoryMethod) {
                    'withCourse'       => Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withSeminar'      => Seminar::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withDigitalAsset' => DigitalAsset::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                }
            )
            ->create(['name' => 'Below Threshold Product']);
        ProductDeliveryOption::where('product_id', $belowThresholdProduct->id)->update([
            'status'         => PublicationStatusEnum::PUBLISHED->value,
            'capacity'       => 30,
            'enrolled_count' => 20,
        ]);

        $withoutCapacityProduct = Product::factory()
            ->withDeliveryOptions(1)
            ->$factoryMethod(
                match ($factoryMethod) {
                    'withCourse'       => Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withSeminar'      => Seminar::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                    'withDigitalAsset' => DigitalAsset::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
                }
            )
            ->create(['name' => 'Without Capacity Product']);
        ProductDeliveryOption::where('product_id', $withoutCapacityProduct->id)->update([
            'status'         => PublicationStatusEnum::PUBLISHED->value,
            'capacity'       => null,
            'enrolled_count' => 20,
        ]);

        $response = $this->getJson(route("api.v1.shop.{$routePrefix}.index", [
            'filter' => [
                'near_capacity_only' => 1,
                'capacity_threshold' => 0.8,
            ],
        ]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['slug' => $nearCapacityProduct->slug])
            ->assertJsonMissing(['slug' => $belowThresholdProduct->slug])
            ->assertJsonMissing(['slug' => $withoutCapacityProduct->slug]);
    })->with('product_types');
});
