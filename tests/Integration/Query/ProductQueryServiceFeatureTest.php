<?php

declare(strict_types=1);

use App\Data\Shop\Product\Course\ProductFilterData;
use App\Data\Shop\Product\Course\ProductListRequestData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Product\AvailabilityStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Enums\Product\ProductableEnum;
use App\Enums\TermStatusEnum;
use App\Models\Category;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\ProductDeliveryOptionDiscountPrice;
use App\Models\Seminar;
use App\Models\Term;
use App\Query\ProductQueryService;
use App\Services\ProductPriceService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\Support\Helpers\TypesenseTestHelper;

describe('ProductQueryService integration', function () {
    describe('get by type', function () {
        beforeEach(function () {
            Product::factory()
                ->withDeliveryOptions(1)
                ->withCategory(1)
                ->withDigitalAsset()
                ->count(6)
                ->create();
            Product::factory()
                ->withDeliveryOptions(1)
                ->withCategory(1)
                ->withCourse()
                ->count(6)
                ->create();
            Product::factory()
                ->withDeliveryOptions(1)
                ->withCategory(1)
                ->withSeminar()
                ->count(6)
                ->create();
            Product::factory()
                ->withDeliveryOptions(1)
                ->withCategory(1)
                ->withCourse()
                ->count(6)
                ->create();
            Product::factory()
                ->withDeliveryOptions(1)
                ->withCategory(1)
                ->withDigitalAsset()
                ->count(6)
                ->create();
            Product::factory()
                ->withDeliveryOptions(1)
                ->withCategory(1)
                ->withSeminar()
                ->count(6)
                ->create();
        });
        it('fetches products with type course', function () {
            $retrievedProducts = ProductQueryService::make()->getCourseList(new ProductListRequestData());
            expect($retrievedProducts->count())->toBe(12);
            /** @var Product $product */
            foreach ($retrievedProducts as $product) {
                expect($product->productable_type)->toBe(ProductableEnum::COURSE->value)
                    ->and($product->relationLoaded('vendor'))->toBeTrue()
                    ->and($product->relationLoaded('categories'))->toBeTrue()
                    ->and($product->relationLoaded('productDeliveryOptions'))->toBeTrue()
                    ->and($product->productDeliveryOptions->first()
                        ->relationLoaded('productDeliveryOptionDiscountPrice'))->toBeTrue()
                    ->and($product->productDeliveryOptions->first()->relationLoaded('teachers'))->toBeTrue()
                    ->and($product->relationLoaded('productable'))->toBeTrue()
                    ->and($product->categories)->toHaveCount(1)
                    ->and($product->productDeliveryOptions)->toHaveCount(1);
            }
        });

        it('fetches products with type seminar', function () {
            $retrievedProducts = ProductQueryService::make()->getSeminarList(new ProductListRequestData());
            expect($retrievedProducts->count())->toBe(12);
            /** @var Product $product */
            foreach ($retrievedProducts as $product) {
                expect($product->productable_type)->toBe(ProductableEnum::SEMINAR->value)
                    ->and($product->relationLoaded('vendor'))->toBeTrue()
                    ->and($product->relationLoaded('categories'))->toBeTrue()
                    ->and($product->relationLoaded('productDeliveryOptions'))->toBeTrue()
                    ->and($product->productDeliveryOptions->first()
                        ->relationLoaded('productDeliveryOptionDiscountPrice'))->toBeTrue()
                    ->and($product->productDeliveryOptions->first()->relationLoaded('teachers'))->toBeTrue()
                    ->and($product->relationLoaded('productable'))->toBeTrue()
                    ->and($product->categories)->toHaveCount(1)
                    ->and($product->productDeliveryOptions)->toHaveCount(1);
            }
        });
        it('fetches products with type digital assets', function () {
            $retrievedProducts = ProductQueryService::make()->getDigitalAssetList(new ProductListRequestData());
            expect($retrievedProducts->count())->toBe(12);
            /** @var Product $product */
            foreach ($retrievedProducts as $product) {
                expect($product->productable_type)->toBe(ProductableEnum::DIGITAL_ASSET->value)
                    ->and($product->relationLoaded('vendor'))->toBeTrue()
                    ->and($product->relationLoaded('categories'))->toBeTrue()
                    ->and($product->relationLoaded('productDeliveryOptions'))->toBeTrue()
                    ->and($product->productDeliveryOptions->first()
                        ->relationLoaded('productDeliveryOptionDiscountPrice'))->toBeTrue()
                    ->and($product->productDeliveryOptions->first()->relationLoaded('teachers'))->toBeTrue()
                    ->and($product->relationLoaded('productable'))->toBeTrue()
                    ->and($product->categories)->toHaveCount(1)
                    ->and($product->productDeliveryOptions)->toHaveCount(1);
            }
        });
    });

    describe('Product listings', function () {
        it('applies search, category, fulfillment_type, and level filters', function () {
            $targetCategory = Category::factory()->create(['slug' => 'laravel-bootcamp']);
            $otherCategory  = Category::factory()->create();

            $targetCourse = Course::factory()->create([
                'full_name'        => 'Laravel Zero to Hero',
                'short_name'       => 'Laravel Bootcamp',
                'difficulty_level' => CourseDifficultyLevelEnum::BEGINNER->value,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
            ]);

            $targetProduct = Product::factory()
                ->withCourse($targetCourse)
                ->create([
                    'name'              => 'Laravel Bootcamp Special',
                    'short_description' => 'Hands-on Laravel training',
                ]);

            $targetProduct->categories()->sync([$targetCategory->id]);
            $targetOption = ProductDeliveryOption::factory()->for($targetProduct)->create([
                'price'            => 150_000,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            ProductDeliveryOptionDiscountPrice::factory()
                ->forProductDeliveryOption($targetOption)
                ->withPrice(140_000)
                ->create();
            $targetProduct = indexProductPrice($targetProduct);

            $otherCourse = Course::factory()->create([
                'full_name'        => 'Symfony From Scratch',
                'short_name'       => 'Symfony Mastery',
                'difficulty_level' => CourseDifficultyLevelEnum::ADVANCED->value,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
            ]);

            $otherProduct = Product::factory()
                ->withCourse($otherCourse)
                ->create([
                    'name'              => 'Symfony Mastery',
                    'short_description' => 'Advanced Symfony topics',
                ]);

            $otherProduct->categories()->sync([$otherCategory->id]);
            ProductDeliveryOption::factory()->for($otherProduct)->create([
                'price'            => 90_000,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
                'fulfillment_type' => FulfillmentTypeEnum::DIGITAL->value,
            ]);
            indexProductPrice($otherProduct);

            $request = ProductListRequestData::from([
                'q'      => 'Laravel',
                'filter' => [
                    'categorySlug'     => $targetCategory->slug,
                    'difficulty_level' => CourseDifficultyLevelEnum::BEGINNER->value,
                    'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
                ],
                'per_page' => 10,
            ]);

            $results = ProductQueryService::make()->getCourseList($request);

            expect($results)->toBeInstanceOf(LengthAwarePaginator::class)
                ->and($results->total())->toBe(1)
                ->and($results->first()->is($targetProduct))->toBeTrue();
        });
        it('filter courses by price range', function () {
            $cheapCourse  = Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED->value]);
            $cheapProduct = Product::factory()
                ->withCourse($cheapCourse)
                ->create(['name' => 'Affordable Course']);
            ProductDeliveryOption::factory()->for($cheapProduct)->create([
                'price'            => 80_000,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
                'fulfillment_type' => FulfillmentTypeEnum::DIGITAL->value,
            ]);
            indexProductPrice($cheapProduct);

            $expensiveCourse  = Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED->value]);
            $expensiveProduct = Product::factory()
                ->withCourse($expensiveCourse)
                ->create(['name' => 'Premium Course']);
            ProductDeliveryOption::factory()->for($expensiveProduct)->create([
                'price'            => 250_000,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($expensiveProduct);

            $request = ProductListRequestData::from([
                'filter' => [
                    'min_price' => 100_000,
                    'max_price' => 300_000,
                ],
            ]);

            $results = ProductQueryService::make()->getCourseList($request);

            expect($results->total())->toBe(1)
                ->and($results->first()->is($expensiveProduct))->toBeTrue();
        });

        it('get semianrs', function () {
            $seminarCategory = Category::factory()->create();

            $seminarProduct = Product::factory()
                ->withSeminar(Seminar::factory()->create())
                ->create(['name' => 'Seminar Product']);
            $seminarProduct->categories()->sync([$seminarCategory->id]);
            ProductDeliveryOption::factory()->for($seminarProduct)->create([
                'price'            => 150_000,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($seminarProduct);

            $request = ProductListRequestData::from([
                'filter' => [
                    'type'         => ProductableEnum::SEMINAR->value,
                    'category_ids' => [$seminarCategory->id],
                ],
            ]);

            $results = ProductQueryService::make()->getSeminarList($request);

            expect($results->total())->toBe(1)
                ->and($results->first()->is($seminarProduct))->toBeTrue();
        });
        it('get digital assets', function () {
            $assetCategory = Category::factory()->create();

            $assetProduct = Product::factory()
                ->withDigitalAsset(DigitalAsset::factory()->create())
                ->create(['name' => 'Asset Product']);
            $assetProduct->categories()->sync([$assetCategory->id]);
            ProductDeliveryOption::factory()->for($assetProduct)->create([
                'price'            => 200_000,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
                'fulfillment_type' => FulfillmentTypeEnum::DIGITAL->value,
            ]);
            indexProductPrice($assetProduct);

            $request = ProductListRequestData::from([
                'filter' => [
                    'type'         => ProductableEnum::DIGITAL_ASSET->value,
                    'category_ids' => [$assetCategory->id],
                ],
            ]);

            $results = ProductQueryService::make()->getDigitalAssetList($request);

            expect($results->total())->toBe(1)
                ->and($results->first()->is($assetProduct))->toBeTrue();
        });
        it('narrows results by product type and categories', function () {
            $seminarCategory = Category::factory()->create();
            $courseCategory  = Category::factory()->create();

            $courseProduct = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'Course Product']);
            $courseProduct->categories()->sync([$courseCategory->id]);
            ProductDeliveryOption::factory()->for($courseProduct)->create([
                'price'            => 110_000,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
                'fulfillment_type' => FulfillmentTypeEnum::DIGITAL->value,
            ]);
            indexProductPrice($courseProduct);

            $seminarProduct = Product::factory()
                ->withSeminar(Seminar::factory()->create())
                ->create(['name' => 'Seminar Product']);
            $seminarProduct->categories()->sync([$seminarCategory->id]);
            ProductDeliveryOption::factory()->for($seminarProduct)->create([
                'price'            => 150_000,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($seminarProduct);

            $request = ProductListRequestData::from([
                'type'   => ProductableEnum::SEMINAR->value,
                'filter' => [
                    'category_ids' => [$seminarCategory->id],
                ],
            ]);

            $results = ProductQueryService::make()->globalSearchProductsDatabase($request);

            expect($results->total())->toBe(1)
                ->and($results->first()->is($seminarProduct))->toBeTrue();
        });

        it('filters by price range and sorts by price ascending', function () {
            $courseProduct = Product::factory()->withCourse(Course::factory()->create())
                ->create(['name' => 'Course A']);
            ProductDeliveryOption::factory()->for($courseProduct)->create([
                'price'            => 90_000,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
                'fulfillment_type' => FulfillmentTypeEnum::DIGITAL->value,
            ]);
            indexProductPrice($courseProduct);

            $seminarProduct = Product::factory()->withSeminar(Seminar::factory()->create())
                ->create(['name' => 'Seminar B']);
            ProductDeliveryOption::factory()->for($seminarProduct)->create([
                'price'            => 180_000,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($seminarProduct);

            $assetProduct = Product::factory()->withDigitalAsset(DigitalAsset::factory()->create())
                ->create(['name' => 'Asset C']);
            ProductDeliveryOption::factory()->for($assetProduct)->create([
                'price'            => 240_000,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
                'fulfillment_type' => FulfillmentTypeEnum::DIGITAL->value,
            ]);
            indexProductPrice($assetProduct);

            $request = ProductListRequestData::from([
                'filter' => [
                    'min_price'      => 80_000,
                    'max_price'      => 200_000,
                    'with_discounts' => false,
                ],
                'sortBy'    => 'price',
                'sortOrder' => 'asc',
            ]);

            $results = ProductQueryService::make()->globalSearchProductsDatabase($request);

            expect($results->total())->toBe(2)
                ->and($results->items()[0]->is($courseProduct->fresh()))->toBeTrue()
                ->and($results->items()[1]->is($seminarProduct->fresh()))->toBeTrue();
        });

        it('filter product by search term matches product name', function () {
            $courseProduct = Product::factory()->withCourse(Course::factory()->create())
                ->create(['name' => 'Learn PHP']);
            ProductDeliveryOption::factory()->for($courseProduct)->create([
                'price'            => 100_000,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
                'fulfillment_type' => FulfillmentTypeEnum::DIGITAL->value,
            ]);
            indexProductPrice($courseProduct);

            $seminarProduct = Product::factory()->withSeminar(Seminar::factory()->create())
                ->create(['name' => 'Mastering JavaScript']);
            ProductDeliveryOption::factory()->for($seminarProduct)->create([
                'price'            => 150_000,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($seminarProduct);

            $assetProduct = Product::factory()->withDigitalAsset(DigitalAsset::factory()->create())
                ->create(['name' => 'CSS Design Patterns']);
            ProductDeliveryOption::factory()->for($assetProduct)->create([
                'price'            => 200_000,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
                'fulfillment_type' => FulfillmentTypeEnum::DIGITAL->value,
            ]);
            indexProductPrice($assetProduct);

            $request = ProductListRequestData::from([
                'q' => 'JavaScript',
            ]);

            $results = ProductQueryService::make()->globalSearchProductsDatabase($request);

            expect($results->total())->toBe(1)
                ->and($results->first()->is($seminarProduct->fresh()))->toBeTrue();
        });

        it('filter product by search term matches data in produtable fields', function () {
            $courseProduct = Product::factory()->withCourse(
                Course::factory()->create([
                    'full_name'   => 'Full Stack Web Development',
                    'short_name'  => 'Web Dev Bootcamp',
                    'description' => 'Learn to build websites from scratch',
                    'status'      => PublicationStatusEnum::PUBLISHED->value,
                ])
            )->create(['name' => 'Web Dev Course']);
            ProductDeliveryOption::factory()->for($courseProduct)->create([
                'price'            => 100_000,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
                'fulfillment_type' => FulfillmentTypeEnum::DIGITAL->value,
            ]);
            indexProductPrice($courseProduct);

            $seminarProduct = Product::factory()->withSeminar(Seminar::factory()->create([
                'full_name'   => 'Data Science Seminar',
                'short_name'  => 'Data Science 101',
                'description' => 'Introduction to data science concepts',
                'status'      => PublicationStatusEnum::PUBLISHED->value,
            ]))->create(['name' => 'Data Science Seminar']);
            ProductDeliveryOption::factory()->for($seminarProduct)->create([
                'price'            => 150_000,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($seminarProduct);

            $assetProduct = Product::factory()->withDigitalAsset(DigitalAsset::factory()->create([
                'full_name'   => 'Graphic Design Essentials',
                'short_name'  => 'Design Basics',
                'description' => 'Learn the fundamentals of graphic design',
                'status'      => PublicationStatusEnum::PUBLISHED->value,
            ]))->create(['name' => 'Design Asset']);
            ProductDeliveryOption::factory()->for($assetProduct)->create([
                'price'            => 200_000,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
                'fulfillment_type' => FulfillmentTypeEnum::DIGITAL->value,
            ]);
            indexProductPrice($assetProduct);

            $request = ProductListRequestData::from([
                'q' => 'data science',
            ]);

            $results = ProductQueryService::make()->globalSearchProductsDatabase($request);
            expect($results->total())->toBe(1)
                ->and($results->first()->is($seminarProduct->fresh()))->toBeTrue();
        });

        it('filter product by discounted products only', function () {
            $discountedProduct = Product::factory()->withCourse(Course::factory()->create())
                ->create(['name' => 'Discounted Course']);
            $discountedOption = ProductDeliveryOption::factory()->for($discountedProduct)->create([
                'price'            => 200_000,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
                'fulfillment_type' => FulfillmentTypeEnum::DIGITAL->value,
            ]);
            ProductDeliveryOptionDiscountPrice::factory()
                ->forProductDeliveryOption($discountedOption)
                ->withPrice(150_000)
                ->create();
            indexProductPrice($discountedProduct);

            $fullPriceProduct = Product::factory()->withCourse(Course::factory()->create())
                ->create(['name' => 'Full Price Course']);
            ProductDeliveryOption::factory()->for($fullPriceProduct)->create([
                'price'            => 180_000,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($fullPriceProduct);

            $request = ProductListRequestData::from([
                'filter' => [
                    'with_discounts' => true,
                ],
            ]);

            $results = ProductQueryService::make()->globalSearchProductsDatabase($request);

            expect($results->total())->toBe(1)
                ->and($results->first()->is($discountedProduct->fresh()))->toBeTrue();
        });
    });

    describe('fluent query helpers', function () {
        it('excludes unavailable products from availableProducts()', function () {
            $validProduct = Product::factory()->withCourse(Course::factory()->create())->create();
            $validOption  = ProductDeliveryOption::factory()->for($validProduct)->create([
                'status'           => PublicationStatusEnum::PUBLISHED->value,
                'price'            => 120_000,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($validProduct);

            Product::factory()->withCourse(Course::factory()->create([
                'status' => PublicationStatusEnum::DRAFT->value,
            ]))->create([
                'name' => 'Draft Course Product',
            ]);

            Product::factory()->withCourse(Course::factory()->create())->create([
                'name'       => 'Invisible Product',
                'is_visible' => false,
            ]);

            $inactiveTerm = Term::factory()->create(['status' => TermStatusEnum::INACTIVE->value]);
            Product::factory()->withCourse(Course::factory()->create())->create([
                'name'    => 'Inactive Term Product',
                'term_id' => $inactiveTerm->id,
            ]);

            $productWithDraftOption = Product::factory()->withCourse(Course::factory()->create())->create([
                'name' => 'Draft Option Product',
            ]);
            ProductDeliveryOption::factory()->for($productWithDraftOption)->create([
                'status' => PublicationStatusEnum::DRAFT->value,
            ]);

            $results = ProductQueryService::make()->availableProducts()->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->is($validProduct->fresh()))->toBeTrue()
                ->and($results->first()->relationLoaded('productDeliveryOptions'))->toBeFalse();

            expect($validOption->status)->toBe(PublicationStatusEnum::PUBLISHED);
        });

        it('searches across productable attributes using deferred constraints', function () {
            $course = Course::factory()->create([
                'full_name' => 'Kotlin Expert Bootcamp',
            ]);

            $product = Product::factory()->withCourse($course)->create([
                'name'              => 'Kotlin Mobile Development Course',
                'short_description' => 'Build Kotlin-powered Android apps',
            ]);
            ProductDeliveryOption::factory()->for($product)->create([
                'price'            => 130_000,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($product);

            $results = ProductQueryService::make()
                ->availableProducts()
                ->search('Kotlin')
                ->get();

            expect($results)->not()->toBeEmpty()
                ->and($results->first()->is($product->fresh()))->toBeTrue();
        });

        it('keeps only discounted products when withDiscounts() is applied', function () {
            $discounted     = Product::factory()->withCourse(Course::factory()->create())->create(['name' => 'Discounted']);
            $discountOption = ProductDeliveryOption::factory()->for($discounted)->create([
                'price'            => 180_000,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            ProductDeliveryOptionDiscountPrice::factory()
                ->forProductDeliveryOption($discountOption)
                ->withPrice(150_000)
                ->create();
            $discounted = indexProductPrice($discounted);

            $fullPrice = Product::factory()->withCourse(Course::factory()->create())->create(['name' => 'Full Price']);
            ProductDeliveryOption::factory()->for($fullPrice)->create([
                'price'            => 160_000,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($fullPrice);

            $results = ProductQueryService::make()
                ->availableProducts()
                ->withDiscounts()
                ->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->is($discounted->fresh()))->toBeTrue();
        });

        it('orders products by popularity when popular() is chained', function () {
            $popularProduct = Product::factory()->withCourse(Course::factory()->create())
                ->create(['name' => 'Popular']);
            $popularOption = ProductDeliveryOption::factory()->for($popularProduct)->create([
                'status'           => PublicationStatusEnum::PUBLISHED->value,
                'price'            => 120_000,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($popularProduct);

            $lessPopularProduct = Product::factory()->withCourse(Course::factory()->create())
                ->create(['name' => 'Less Popular']);
            $lessPopularOption = ProductDeliveryOption::factory()->for($lessPopularProduct)->create([
                'status'           => PublicationStatusEnum::PUBLISHED->value,
                'price'            => 120_000,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($lessPopularProduct);

            $order       = Order::factory()->create();
            $secondOrder = Order::factory()->create();

            OrderItem::factory()->create([
                'order_id'                   => $order->id,
                'product_delivery_option_id' => $popularOption->id,
            ]);
            OrderItem::factory()->create([
                'order_id'                   => $secondOrder->id,
                'product_delivery_option_id' => $popularOption->id,
            ]);
            OrderItem::factory()->create([
                'order_id'                   => $order->id,
                'product_delivery_option_id' => $lessPopularOption->id,
            ]);

            $results = ProductQueryService::make()
                ->availableProducts()
                ->popular()
                ->limit(2)
                ->get();

            expect($results)->toHaveCount(2)
                ->and($results->first()->is($popularProduct->fresh()))->toBeTrue()
                ->and($results->last()->is($lessPopularProduct->fresh()))->toBeTrue();
        });
        it('get products using byInstructor', function () {
            $instructor = App\Models\Teacher::factory()->create();

            $courseByInstructor = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'Instructor Course']);
            $pdo = ProductDeliveryOption::factory()->for($courseByInstructor)->create([
                'price'            => 120_000,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);

            $pdo->teachers()->sync([$instructor->id]);

            indexProductPrice($courseByInstructor);

            $otherCourse = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'Other Course']);
            ProductDeliveryOption::factory()->for($otherCourse)->create([
                'price'            => 130_000,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($otherCourse);
            $products = ProductQueryService::make()
                ->availableProducts()
                ->byInstructor($instructor->id)
                ->get();

            expect($products->count())->toBe(1)
                ->and($products->first()->is($courseByInstructor->fresh()))->toBeTrue();
        });

        it('return correct data when using forDetail()', function () {
            $course = Course::factory()
                ->withMedia()
                ->create(['status' => PublicationStatusEnum::PUBLISHED->value]);
            $product = Product::factory()
                ->withCategory()
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
                ->withCourse($course)
                ->create();
            $product = indexProductPrice($product);

            $fetchedProduct = ProductQueryService::make()
                ->ofType(ProductableEnum::COURSE)
                ->availableProducts()
                ->forDetail()
                ->getQuery()
                ->where('products.id', $product->id)
                ->first();

            expect($fetchedProduct)->not()->toBeNull()
                ->and($fetchedProduct->is($product))->toBeTrue()
                ->and($fetchedProduct->relationLoaded('vendor'))->toBeTrue()
                ->and($fetchedProduct->relationLoaded('categories'))->toBeTrue()
                ->and($fetchedProduct->relationLoaded('productDeliveryOptions'))->toBeTrue()
                ->and($fetchedProduct->productDeliveryOptions->first()
                    ->relationLoaded('productDeliveryOptionDiscountPrice'))->toBeTrue()
                ->and($fetchedProduct->productDeliveryOptions->first()->relationLoaded('teachers'))->toBeTrue()
                ->and($fetchedProduct->relationLoaded('productable'))->toBeTrue()
                ->and($fetchedProduct->productable->relationLoaded('media'))->toBeTrue()
                ->and($fetchedProduct->categories)->toHaveCount(1)
                ->and($fetchedProduct->productDeliveryOptions)->toHaveCount(2);
        });

        it('return correct data when using forList()', function () {
            $course = Course::factory()
                ->withMedia()
                ->create(['status' => PublicationStatusEnum::PUBLISHED->value]);
            $product = Product::factory()
                ->withCategory()
                ->withDeliveryOptions(realData: [
                    [
                        'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE,
                        'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE,
                        'price'            => 1000000,
                    ],
                ])
                ->withCourse($course)
                ->create();
            $product = indexProductPrice($product);

            $fetchedProduct = ProductQueryService::make()
                ->ofType(ProductableEnum::COURSE)
                ->availableProducts()
                ->forListing()
                ->getQuery()
                ->where('products.id', $product->id)
                ->first();

            expect($fetchedProduct)->not()->toBeNull()
                ->and($fetchedProduct->is($product))->toBeTrue()
                ->and($fetchedProduct->relationLoaded('vendor'))->toBeTrue()
                ->and($fetchedProduct->relationLoaded('categories'))->toBeTrue()
                ->and($fetchedProduct->relationLoaded('productDeliveryOptions'))->toBeTrue()
                ->and($fetchedProduct->productDeliveryOptions->first()
                    ->relationLoaded('productDeliveryOptionDiscountPrice'))->toBeTrue()
                ->and($fetchedProduct->productDeliveryOptions->first()->relationLoaded('teachers'))->toBeTrue()
                ->and($fetchedProduct->relationLoaded('productable'))->toBeTrue()
                ->and($fetchedProduct->productable->relationLoaded('media'))->toBeFalse()
                ->and($fetchedProduct->categories)->toHaveCount(1)
                ->and($fetchedProduct->productDeliveryOptions)->toHaveCount(1);
        });

        it('does not return full products when includeFullProducts is false', function () {
            $product = Product::factory()
                ->withCategory()
                ->withCourse()
                ->create();
            $deliveryOption = ProductDeliveryOption::factory()
                ->for($product)
                ->create([
                    'name'             => 'Main Option',
                    'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE,
                    'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE,
                    'price'            => 1000000,
                    'capacity'         => 1,
                ]);
            $order = Order::factory()
                ->create();
            $orderItem = OrderItem::factory()
                ->create([
                    'order_id'                   => $order->id,
                    'product_delivery_option_id' => $deliveryOption->id,
                ]);
            App\Models\Enrollment::factory()
                ->create([
                    'order_item_id'              => $orderItem->id,
                    'order_id'                   => $order->id,
                    'product_delivery_option_id' => $deliveryOption->id,
                    'enrollment_status'          => EnrollmentStatusEnum::PENDING_PROVISIONING,
                ]);
            Product::factory()
                ->withCategory()
                ->withDeliveryOptions(realData: [
                    [
                        'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE,
                        'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE,
                        'price'            => 1000000,
                        'capacity'         => 1,
                    ],
                ])
                ->withCourse()
                ->count(3)
                ->create();

            $fetchedProducts = ProductQueryService::make()
                ->ofType(ProductableEnum::COURSE)
                ->availableProducts()
                ->withoutFullProducts()
                ->get();

            expect($fetchedProducts)->not()->toBeNull()
                ->and($fetchedProducts)->toHaveCount(3);
        });

        it('filter products by category slugs', function () {
            $categoryA = Category::factory()->create(['slug' => 'category-a']);
            $categoryB = Category::factory()->create(['slug' => 'category-b']);

            $productA = Product::factory()->withDeliveryOptions()->withCourse(Course::factory()->create())
                ->create(['name' => 'Product A']);
            $productA->categories()->sync([$categoryA->id]);

            $productB = Product::factory()->withDeliveryOptions()->withCourse(Course::factory()->create())
                ->create(['name' => 'Product B']);
            $productB->categories()->sync([$categoryB->id]);

            $results = ProductQueryService::make()
                ->availableProducts()
                ->inCategories([$categoryA->slug])
                ->get();
            expect($results)->toHaveCount(1)
                ->and($results->first()->is($productA->fresh()))->toBeTrue();
        });
        it('filter products by category ids', function () {
            $categoryA = Category::factory()->create(['slug' => 'category-a']);
            $categoryB = Category::factory()->create(['slug' => 'category-b']);

            $productA = Product::factory()->withDeliveryOptions()->withCourse(Course::factory()->create())
                ->create(['name' => 'Product A']);
            $productA->categories()->sync([$categoryA->id]);

            $productB = Product::factory()->withDeliveryOptions()->withCourse(Course::factory()->create())
                ->create(['name' => 'Product B']);
            $productB->categories()->sync([$categoryB->id]);

            $results = ProductQueryService::make()
                ->availableProducts()
                ->inCategoryIds([$categoryA->id])
                ->get();
            expect($results)->toHaveCount(1)
                ->and($results->first()->is($productA->fresh()))->toBeTrue();
        });

        it('filters products by featured flag', function () {
            $featuredProduct = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'Featured Product', 'is_featured' => true]);
            ProductDeliveryOption::factory()->for($featuredProduct)->create([
                'price'            => 100,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($featuredProduct);

            $regularProduct = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'Regular Product', 'is_featured' => false]);
            ProductDeliveryOption::factory()->for($regularProduct)->create([
                'price'            => 100,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($regularProduct);

            $results = ProductQueryService::make()
                ->availableProducts()
                ->featured()
                ->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->is($featuredProduct->fresh()))->toBeTrue();
        });

        it('filters products by availability now - active registration and content', function () {
            $now = now();

            // Product with all windows open
            $activeProduct = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'Active Product']);
            ProductDeliveryOption::factory()->for($activeProduct)->create([
                'price'                   => 100,
                'registration_start_date' => $now->clone()->subDays(5),
                'registration_end_date'   => $now->clone()->addDays(5),
                'available_from'          => $now->clone()->subDays(3),
                'available_to'            => $now->clone()->addDays(10),
                'fulfillment_type'        => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($activeProduct);

            // Product with registration window closed
            $closedRegProduct = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'Closed Registration']);
            ProductDeliveryOption::factory()->for($closedRegProduct)->create([
                'price'                   => 100,
                'registration_start_date' => $now->clone()->subDays(10),
                'registration_end_date'   => $now->clone()->subDays(1),
                'available_from'          => $now->clone()->subDays(3),
                'available_to'            => $now->clone()->addDays(10),
                'fulfillment_type'        => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($closedRegProduct);

            // Product with content not yet available
            $futureContentProduct = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'Future Content']);
            ProductDeliveryOption::factory()->for($futureContentProduct)->create([
                'price'                   => 100,
                'registration_start_date' => $now->clone()->subDays(5),
                'registration_end_date'   => $now->clone()->addDays(5),
                'available_from'          => $now->clone()->addDays(1),
                'available_to'            => $now->clone()->addDays(10),
                'fulfillment_type'        => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($futureContentProduct);

            $results = ProductQueryService::make()
                ->availableNow()
                ->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->is($activeProduct->fresh()))->toBeTrue();
        });

        it('filters products by availability status', function () {
            $now = now();

            // Past
            $pastProduct = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'Active Product']);
            ProductDeliveryOption::factory()->for($pastProduct)->create([
                'price'                   => 100,
                'available_from'          => $now->clone()->subDays(3),
                'available_to'            => $now->clone()->subDays(1),
                'fulfillment_type'        => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($pastProduct);

            // Ongoing
            $ongoingProduct = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'Closed Registration']);
            ProductDeliveryOption::factory()->for($ongoingProduct)->create([
                'price'                   => 100,
                'available_from'          => $now->clone()->subDays(3),
                'available_to'            => $now->clone()->addDays(1),
                'fulfillment_type'        => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($ongoingProduct);

            // Upcoming
            $futureContentProduct = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'Future Content']);
            ProductDeliveryOption::factory()->for($futureContentProduct)->create([
                'price'                   => 100,
                'available_from'          => $now->clone()->addDays(2),
                'available_to'            => $now->clone()->addDays(10),
                'fulfillment_type'        => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($futureContentProduct);

            $results = ProductQueryService::make()
                ->availabilityStatus(AvailabilityStatusEnum::PAST)
                ->getQuery()
                ->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->is($pastProduct->fresh()))->toBeTrue();

            $results = ProductQueryService::make()
                ->availabilityStatus(AvailabilityStatusEnum::ONGOING)
                ->getQuery()
                ->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->is($ongoingProduct->fresh()))->toBeTrue();

            $results = ProductQueryService::make()
                ->availabilityStatus(AvailabilityStatusEnum::UPCOMING)
                ->getQuery()
                ->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->is($futureContentProduct->fresh()))->toBeTrue();
        });

        it('filters products by registration window', function () {
            $targetDate = Carbon::now();

            // Product with registration starting before target date
            $earlyRegProduct = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'Early Registration']);
            ProductDeliveryOption::factory()->for($earlyRegProduct)->create([
                'price'                   => 100,
                'registration_start_date' => $targetDate->clone()->subDays(10),
                'registration_end_date'   => $targetDate->clone()->addDays(10),
                'fulfillment_type'        => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($earlyRegProduct);

            // Product with registration starting after target date
            $lateRegProduct = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'Late Registration']);
            ProductDeliveryOption::factory()->for($lateRegProduct)->create([
                'price'                   => 100,
                'registration_start_date' => $targetDate->clone()->addDays(15),
                'registration_end_date'   => $targetDate->clone()->addDays(25),
                'fulfillment_type'        => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($lateRegProduct);

            // Only get products that START registration on or after target date
            $results = ProductQueryService::make()
                ->registrationWindow(from: $targetDate->addDays(11))
                ->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->is($lateRegProduct->fresh()))->toBeTrue();
        });

        it('filters products by registration window - end date constraint', function () {
            $toDate = Carbon::now();

            // Product with registration ending before target date - NO OVERLAP
            $earlyEndProduct = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'Early End']);
            ProductDeliveryOption::factory()->for($earlyEndProduct)->create([
                'price'                   => 100,
                'registration_start_date' => $toDate->clone()->subDays(20),
                'registration_end_date'   => $toDate->clone()->subDays(5),
                'fulfillment_type'        => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($earlyEndProduct);

            // Product with registration overlapping target date - OVERLAPS
            $overlappingProduct = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'Overlapping']);
            ProductDeliveryOption::factory()->for($overlappingProduct)->create([
                'price'                   => 100,
                'registration_start_date' => $toDate->clone()->subDays(10),
                'registration_end_date'   => $toDate->clone()->addDays(10),
                'fulfillment_type'        => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($overlappingProduct);

            // Product with registration starting after target date - NO OVERLAP
            $futureProduct = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'Future Registration']);
            ProductDeliveryOption::factory()->for($futureProduct)->create([
                'price'                   => 100,
                'registration_start_date' => $toDate->clone()->addDays(5),
                'registration_end_date'   => $toDate->clone()->addDays(20),
                'fulfillment_type'        => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($futureProduct);

            // Get products that overlap with window ending at target date
            // Overlap logic: registration_start_date <= toDate AND registration_end_date >= unbounded start
            $results = ProductQueryService::make()
                ->registrationWindow(to: $toDate)
                ->get();

            expect($results)->toHaveCount(2)
                ->and($results->pluck('name')->toArray())->toContain('Early End', 'Overlapping')
                ->and($results->pluck('name')->toArray())->not()->toContain('Future Registration');
        });

        it('filters products by registration window - both from and to dates', function () {
            $fromDate = Carbon::now();
            $toDate   = $fromDate->clone()->addDays(30);

            // Product fully contained within the target range
            $inRangeProduct = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'In Range']);
            ProductDeliveryOption::factory()->for($inRangeProduct)->create([
                'price'                   => 100,
                'registration_start_date' => $fromDate->clone()->addDays(5),
                'registration_end_date'   => $toDate->clone()->subDays(5),
                'fulfillment_type'        => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($inRangeProduct);

            // Product that overlaps the range (extends beyond boundaries)
            $overlappingProduct = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'Overlapping']);
            ProductDeliveryOption::factory()->for($overlappingProduct)->create([
                'price'                   => 100,
                'registration_start_date' => $fromDate->clone()->subDays(10),
                'registration_end_date'   => $toDate->clone()->addDays(10),
                'fulfillment_type'        => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($overlappingProduct);

            // Product that does not overlap at all
            $outsideProduct = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'Outside Range']);
            ProductDeliveryOption::factory()->for($outsideProduct)->create([
                'price'                   => 100,
                'registration_start_date' => $toDate->clone()->addDays(5),
                'registration_end_date'   => $toDate->clone()->addDays(20),
                'fulfillment_type'        => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($outsideProduct);

            // Get products that overlap with the specified window
            $results = ProductQueryService::make()
                ->registrationWindow(from: $fromDate, to: $toDate)
                ->get();

            expect($results)->toHaveCount(2)
                ->and($results->pluck('name')->toArray())->toContain('In Range', 'Overlapping')
                ->and($results->pluck('name')->toArray())->not()->toContain('Outside Range');
        });

        it('filters products by availability window', function () {
            $targetDate = Carbon::now();

            // Product available before target date
            $earlyAvailProduct = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'Early Available']);
            ProductDeliveryOption::factory()->for($earlyAvailProduct)->create([
                'price'            => 100,
                'available_from'   => $targetDate->clone()->subDays(20),
                'available_to'     => $targetDate->clone()->subDays(5),
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($earlyAvailProduct);

            // Product available after target date
            $laterAvailProduct = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'Later Available']);
            ProductDeliveryOption::factory()->for($laterAvailProduct)->create([
                'price'            => 100,
                'available_from'   => $targetDate->clone()->addDays(5),
                'available_to'     => $targetDate->clone()->addDays(20),
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($laterAvailProduct);

            // Only get products available on or after target date
            $results = ProductQueryService::make()
                ->availabilityWindow(from: $targetDate)
                ->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->is($laterAvailProduct->fresh()))->toBeTrue();
        });

        it('filters products by availability window - end date constraint', function () {
            $toDate = Carbon::now();

            // Product available ending before target date - NO OVERLAP
            $earlyEndAvailProduct = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'Early End Available']);
            ProductDeliveryOption::factory()->for($earlyEndAvailProduct)->create([
                'price'            => 100,
                'available_from'   => $toDate->clone()->subDays(20),
                'available_to'     => $toDate->clone()->subDays(5),
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($earlyEndAvailProduct);

            // Product available overlapping with target date - OVERLAPS
            $lateEndAvailProduct = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'Late End Available']);
            ProductDeliveryOption::factory()->for($lateEndAvailProduct)->create([
                'price'            => 100,
                'available_from'   => $toDate->clone()->subDays(10),
                'available_to'     => $toDate->clone()->addDays(10),
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($lateEndAvailProduct);

            // Product available starting after target date - NO OVERLAP
            $futureAvailProduct = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'Future Available']);
            ProductDeliveryOption::factory()->for($futureAvailProduct)->create([
                'price'            => 100,
                'available_from'   => $toDate->clone()->addDays(5),
                'available_to'     => $toDate->clone()->addDays(20),
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($futureAvailProduct);

            // Get products that overlap with window ending at target date
            // Overlap logic: available_from <= toDate AND available_to >= unbounded start
            $results = ProductQueryService::make()
                ->availabilityWindow(to: $toDate)
                ->get();

            expect($results)->toHaveCount(2)
                ->and($results->pluck('name')->toArray())->toContain('Early End Available', 'Late End Available')
                ->and($results->pluck('name')->toArray())->not()->toContain('Future Available');
        });

        it('filters products by availability window - both from and to dates', function () {
            $fromDate = Carbon::now();
            $toDate   = $fromDate->clone()->addDays(30);

            // Product fully contained within the target range
            $inRangeProduct = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'In Range']);
            ProductDeliveryOption::factory()->for($inRangeProduct)->create([
                'price'            => 100,
                'available_from'   => $fromDate->clone()->addDays(5),
                'available_to'     => $toDate->clone()->subDays(5),
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($inRangeProduct);

            // Product that overlaps the range (extends beyond boundaries)
            $overlappingProduct = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'Overlapping']);
            ProductDeliveryOption::factory()->for($overlappingProduct)->create([
                'price'            => 100,
                'available_from'   => $fromDate->clone()->subDays(10),
                'available_to'     => $toDate->clone()->addDays(10),
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($overlappingProduct);

            // Product that does not overlap at all
            $outsideProduct = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'Outside Range']);
            ProductDeliveryOption::factory()->for($outsideProduct)->create([
                'price'            => 100,
                'available_from'   => $toDate->clone()->addDays(5),
                'available_to'     => $toDate->clone()->addDays(20),
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($outsideProduct);

            // Get products that overlap with the specified window
            $results = ProductQueryService::make()
                ->availabilityWindow(from: $fromDate, to: $toDate)
                ->get();

            expect($results)->toHaveCount(2)
                ->and($results->pluck('name')->toArray())->toContain('In Range', 'Overlapping')
                ->and($results->pluck('name')->toArray())->not()->toContain('Outside Range');
        });

        it('handles null dates in availability and registration windows', function () {
            $targetDate = Carbon::now();

            // Product with null registration dates (always open)
            $openRegProduct = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'Open Registration']);
            ProductDeliveryOption::factory()->for($openRegProduct)->create([
                'price'                   => 100,
                'registration_start_date' => null,
                'registration_end_date'   => null,
                'available_from'          => null,
                'available_to'            => null,
                'fulfillment_type'        => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($openRegProduct);

            $results = ProductQueryService::make()
                ->availableNow()
                ->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->is($openRegProduct->fresh()))->toBeTrue();
        });

        it('filters with course difficulty level', function () {
            $beginnerCourse = Course::factory()->create([
                'difficulty_level' => CourseDifficultyLevelEnum::BEGINNER->value,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
            ]);
            $beginnerProduct = Product::factory()
                ->withCourse($beginnerCourse)
                ->create(['name' => 'Beginner Course']);
            ProductDeliveryOption::factory()->for($beginnerProduct)->create([
                'price'            => 50,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($beginnerProduct);

            $advancedCourse = Course::factory()->create([
                'difficulty_level' => CourseDifficultyLevelEnum::ADVANCED->value,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
            ]);
            $advancedProduct = Product::factory()
                ->withCourse($advancedCourse)
                ->create(['name' => 'Advanced Course']);
            ProductDeliveryOption::factory()->for($advancedProduct)->create([
                'price'            => 200,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($advancedProduct);

            $results = ProductQueryService::make()
                ->availableProducts()
                ->byCourseLevel(CourseDifficultyLevelEnum::ADVANCED)
                ->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->is($advancedProduct->fresh()))->toBeTrue();
        });

        it('filters by fulfillment types', function () {
            $onlineProduct = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'Online Course']);
            ProductDeliveryOption::factory()->for($onlineProduct)->create([
                'price'            => 100,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);
            indexProductPrice($onlineProduct);

            $digitalProduct = Product::factory()
                ->withCourse(Course::factory()->create())
                ->create(['name' => 'Digital Asset']);
            ProductDeliveryOption::factory()->for($digitalProduct)->create([
                'price'            => 50,
                'fulfillment_type' => FulfillmentTypeEnum::DIGITAL->value,
            ]);
            indexProductPrice($digitalProduct);

            $results = ProductQueryService::make()
                ->availableProducts()
                ->byFulfillmentTypes([FulfillmentTypeEnum::DIGITAL->value])
                ->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->is($digitalProduct->fresh()))->toBeTrue();
        });

        it('combines multiple filters - difficulty, fulfillment, and category', function () {
            $category = Category::factory()->create(['slug' => 'advanced-digital']);

            $advancedCourse = Course::factory()->create([
                'difficulty_level' => CourseDifficultyLevelEnum::ADVANCED->value,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
            ]);
            $advancedProduct = Product::factory()
                ->withCourse($advancedCourse)
                ->create(['name' => 'Advanced Digital Course']);
            $advancedProduct->categories()->sync([$category->id]);
            ProductDeliveryOption::factory()->for($advancedProduct)->create([
                'price'            => 200,
                'fulfillment_type' => FulfillmentTypeEnum::DIGITAL->value,
            ]);
            indexProductPrice($advancedProduct);

            // Beginner course - should be excluded
            $beginnerCourse = Course::factory()->create([
                'difficulty_level' => CourseDifficultyLevelEnum::BEGINNER->value,
                'status'           => PublicationStatusEnum::PUBLISHED->value,
            ]);
            $beginnerProduct = Product::factory()
                ->withCourse($beginnerCourse)
                ->create(['name' => 'Beginner Digital Course']);
            $beginnerProduct->categories()->sync([$category->id]);
            ProductDeliveryOption::factory()->for($beginnerProduct)->create([
                'price'            => 50,
                'fulfillment_type' => FulfillmentTypeEnum::DIGITAL->value,
            ]);
            indexProductPrice($beginnerProduct);

            $results = ProductQueryService::make()
                ->availableProducts()
                ->inCategories([$category->slug])
                ->byCourseLevel(CourseDifficultyLevelEnum::ADVANCED)
                ->byFulfillmentTypes([FulfillmentTypeEnum::DIGITAL->value])
                ->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->is($advancedProduct->fresh()))->toBeTrue();
        });

    });

    function indexProductPrice(Product $product): Product
    {
        /** @var ProductPriceService $service */
        $service = app(ProductPriceService::class);
        $service->updatePriceIndex($product->fresh());

        return $product->fresh(['productPrice', 'productDeliveryOptions.productDeliveryOptionDiscountPrice']);
    }
});
describe('ProductQueryService - globalSearch', function () {
    it('uses Typesense when available', function () {
        TypesenseTestHelper::skipIfTypesenseUnavailable();

        $requestData = new ProductListRequestData(
            filter: null,
            q: 'test',
            page: 1,
            per_page: 15,
        );
        TypesenseTestHelper::regenerateIndex();
        $results = ProductQueryService::make()->globalSearch($requestData);

        expect($results)->toBeInstanceOf(LengthAwarePaginator::class)
            ->and($results->perPage())->toBe(15);
    });

    it('uses database fallback when Typesense is not available', function () {
        // Force database fallback by setting wrong driver
        Config::set('scout.driver', 'database');

        $requestData = new ProductListRequestData(
            filter: null,
            q: 'test',
            page: 1,
            per_page: 15,
        );

        $results = ProductQueryService::make()->globalSearch($requestData);

        expect($results)->toBeInstanceOf(LengthAwarePaginator::class);
    });

    it('uses database fallback when Typesense throws exception', function () {
        // Even if Typesense is configured, exceptions should trigger fallback
        // This test verifies the try-catch logic
        Config::set('scout.driver', 'typesense');
        Config::set('scout.typesense.client-settings.api_key', 'invalid_key');

        $requestData = new ProductListRequestData(
            filter: null,
            q: 'test',
            page: 1,
            per_page: 15,
        );

        // Should not throw exception; should gracefully fall back to database
        $results = ProductQueryService::make()->globalSearch($requestData);

        expect($results)->toBeInstanceOf(LengthAwarePaginator::class);
    });

    it('returns paginated results from database search', function () {
        Config::set('scout.driver', 'database');

        $filterData = new ProductFilterData(
            category_slugs: null,
            fulfillment_types: [],
            difficulty_level: null,
            min_price: null,
            max_price: null,
            with_discounts: null,
            is_available_now: null,
            near_capacity_only: null,
            capacity_threshold: null,
            registration_starts_after: null,
            registration_ends_before: null,
            available_from: null,
            available_to: null,
        );

        $requestData = new ProductListRequestData(
            filter: $filterData,
            q: 'course',
            type: null,
            page: 1,
            per_page: 10,
        );

        $results = ProductQueryService::make()->globalSearch($requestData);

        expect($results)->toBeInstanceOf(LengthAwarePaginator::class)
            ->and($results->perPage())->toBe(10);
    });
});
