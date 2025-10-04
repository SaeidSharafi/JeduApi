<?php

declare(strict_types=1);

use App\Data\Shop\Product\Course\CourseListRequestData;
use App\Data\Shop\Product\Course\ProductFilterData;
use App\Data\Shop\Product\Course\ProductListRequestData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\EnrollmentStatusEnum;
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
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\Support\TypesenseTestHelper;

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
            $retrievedProducts = ProductQueryService::make()->getCourseList(new CourseListRequestData());
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

            $request = CourseListRequestData::from([
                'search' => 'Laravel',
                'filter' => [
                    'categorySlug'     => $targetCategory->slug,
                    'level'            => CourseDifficultyLevelEnum::BEGINNER->value,
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

            $request = CourseListRequestData::from([
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
                'filter' => [
                    'type'         => ProductableEnum::SEMINAR->value,
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
                'search' => 'JavaScript',
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
                'search' => 'data science',
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
            search: 'test',
            page: 1,
            per_page: 15,
        );

        $results = ProductQueryService::make()->globalSearch($requestData);

        expect($results)->toBeInstanceOf(LengthAwarePaginator::class)
            ->and($results->perPage())->toBe(15);
    });

    it('uses database fallback when Typesense is not available', function () {
        // Force database fallback by setting wrong driver
        Config::set('scout.driver', 'database');

        $requestData = new ProductListRequestData(
            filter: null,
            search: 'test',
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
            search: 'test',
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
            category_ids: null,
            type: null,
            min_price: null,
            max_price: null,
            with_discounts: null,
        );

        $requestData = new ProductListRequestData(
            filter: $filterData,
            search: 'course',
            page: 1,
            per_page: 10,
        );

        $results = ProductQueryService::make()->globalSearch($requestData);

        expect($results)->toBeInstanceOf(LengthAwarePaginator::class)
            ->and($results->perPage())->toBe(10);
    });
});
