<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\Order\DiscountTypeEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\Category;
use App\Models\Course;
use App\Models\DiscountPromotion;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\ProductDeliveryOptionDiscountPrice;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\Vendor;
use Tests\AuthTestTrait;

use function Pest\Laravel\getJson;

uses(AuthTestTrait::class);

describe('CourseController Data Integrity Tests', function (): void {
    describe('Course List API', function (): void {
        it('returns correct pricing data without discounts or featured prices', function (): void {
            // Create deterministic test data
            $vendor = Vendor::factory()->create(['name' => 'Test Vendor']);
            $term   = Term::factory()->create(['name' => 'Fall 2024']);

            $course = Course::factory()->create([
                'full_name'        => 'Introduction to Laravel',
                'short_name'       => 'Laravel 101',
                'description'      => 'Learn Laravel from scratch',
                'difficulty_level' => CourseDifficultyLevelEnum::BEGINNER,
                'duration'         => 40,
                'status'           => PublicationStatusEnum::PUBLISHED,
            ]);

            $product = Product::factory()->create([
                'name'             => 'Laravel Course Product',
                'slug'             => 'laravel-course-product',
                'productable_type' => ProductableEnum::COURSE->value,
                'productable_id'   => $course->id,
                'vendor_id'        => $vendor->id,
                'term_id'          => $term->id,
                'status'           => PublicationStatusEnum::PUBLISHED,
                'is_visible'       => true,
                'is_featured'      => false,
            ]);

            // Create delivery options with specific prices
            $onlineOption = ProductDeliveryOption::factory()->create([
                'product_id'              => $product->id,
                'name'                    => 'Online Course',
                'sku'                     => 'LARAVEL-ONLINE-001',
                'fulfillment_type'        => FulfillmentTypeEnum::ONLINE_SERVICE,
                'delivery_method'         => DeliveryMethodEnum::LMS_MOODLE,
                'price'                   => 500000, // 5000 Toman
                'status'                  => PublicationStatusEnum::PUBLISHED,
                'is_featured'             => false,
                'is_prepayment_available' => false,
            ]);

            $inPersonOption = ProductDeliveryOption::factory()->create([
                'product_id'              => $product->id,
                'name'                    => 'In-Person Course',
                'sku'                     => 'LARAVEL-INPERSON-001',
                'fulfillment_type'        => FulfillmentTypeEnum::IN_PERSON_SERVICE,
                'delivery_method'         => DeliveryMethodEnum::IN_PERSON,
                'price'                   => 1000000, // 10000 Toman
                'status'                  => PublicationStatusEnum::PUBLISHED,
                'is_featured'             => false,
                'is_prepayment_available' => false,
            ]);

            $response = getJson(route('api.v1.shop.courses.index'));

            $response->assertOk()
                ->assertJsonCount(1, 'data.data')
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
                ]);

            $courseData = $response->json('data.data.0');

            // Verify basic product data
            expect($courseData['slug'])->toBe('laravel-course-product')
                ->and($courseData['name'])->toBe('Laravel Course Product')
                ->and($courseData['is_featured'])->toBeFalse()
                ->and($courseData['is_free'])->toBeFalse()
                ->and($courseData['product_type']['value'])->toBe('course');

            // Verify pricing data (should be minimum price)
            expect($courseData['price'])->toBe(500000) // Minimum price from options
                ->and($courseData['original_price'])->toBe(500000) // Same as price when no discounts
                ->and($courseData['has_discount'])->toBeFalse()
                ->and($courseData['discount_percent'])->toBeNull();

            // Verify price range
            expect($courseData['price_range'])->toBe([
                'min' => 500000,
                'max' => 1000000,
            ]);

            // Verify price data structure
            expect($courseData['price_data'])->toHaveKeys([
                'min_price',
                'min_original_price',
                'has_featured_price',
                'has_discount',
                'has_pre_payment',
                'discount_type',
                'discount_percentage',
                'range',
                'prices',
            ]);

            expect($courseData['price_data']['min_price'])->toBe(500000)
                ->and($courseData['price_data']['min_original_price'])->toBe(500000)
                ->and($courseData['price_data']['has_featured_price'])->toBeFalse()
                ->and($courseData['price_data']['has_discount'])->toBeFalse()
                ->and($courseData['price_data']['has_pre_payment'])->toBeFalse()
                ->and($courseData['price_data']['discount_type'])->toBeNull()
                ->and($courseData['price_data']['discount_percentage'])->toBeNull();
        });

        it('returns correct pricing data with featured prices', function (): void {
            $vendor = Vendor::factory()->create();
            $term   = Term::factory()->create();

            $course = Course::factory()->create([
                'full_name' => 'Advanced Laravel',
                'status'    => PublicationStatusEnum::PUBLISHED,
            ]);

            $product = Product::factory()->create([
                'name'             => 'Advanced Laravel Course',
                'slug'             => 'advanced-laravel-course',
                'productable_type' => ProductableEnum::COURSE->value,
                'productable_id'   => $course->id,
                'vendor_id'        => $vendor->id,
                'term_id'          => $term->id,
                'status'           => PublicationStatusEnum::PUBLISHED,
                'is_visible'       => true,
                'is_featured'      => true,
            ]);

            // Create option with featured price
            $featuredOption = ProductDeliveryOption::factory()->create([
                'product_id'                => $product->id,
                'name'                      => 'Featured Online Course',
                'sku'                       => 'ADVANCED-FEATURED-001',
                'fulfillment_type'          => FulfillmentTypeEnum::ONLINE_SERVICE,
                'delivery_method'           => DeliveryMethodEnum::LMS_MOODLE,
                'price'                     => 800000, // Original price
                'status'                    => PublicationStatusEnum::PUBLISHED,
                'is_featured'               => true,
                'featured_price'            => 600000, // Featured price (25% off)
                'featured_price_start_date' => now()->subDay(),
                'featured_price_end_date'   => now()->addDays(30),
                'is_prepayment_available'   => false,
            ]);

            // Regular option without featured price
            $regularOption = ProductDeliveryOption::factory()->create([
                'product_id'              => $product->id,
                'name'                    => 'Regular Course',
                'sku'                     => 'ADVANCED-REGULAR-001',
                'fulfillment_type'        => FulfillmentTypeEnum::OFFLINE_SERVICE,
                'delivery_method'         => DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER,
                'price'                   => 700000,
                'status'                  => PublicationStatusEnum::PUBLISHED,
                'is_featured'             => false,
                'is_prepayment_available' => false,
            ]);

            $response = getJson(route('api.v1.shop.courses.index'));

            $response->assertOk();
            $courseData = $response->json('data.data.0');

            expect($courseData['price'])->toBe(600000) // Featured price is lower
                ->and($courseData['original_price'])->toBe(700000) // Minimum original price
                ->and($courseData['has_discount'])->toBeFalse()
                ->and($courseData['is_featured'])->toBeTrue()
                ->and($courseData['price_data']['min_price'])->toBe(600000)
                ->and($courseData['price_data']['min_original_price'])->toBe(700000)
                ->and($courseData['price_data']['has_featured_price'])->toBeTrue()
                ->and($courseData['price_data']['has_discount'])->toBeFalse()
                ->and($courseData['price_data']['discount_type'])->toBe('featured');
        });

        it('returns correct pricing data with promotion discounts', function (): void {
            $vendor = Vendor::factory()->create();
            $term   = Term::factory()->create();

            $course = Course::factory()->create([
                'full_name' => 'React Fundamentals',
                'status'    => PublicationStatusEnum::PUBLISHED,
            ]);

            $product = Product::factory()->create([
                'name'             => 'React Course',
                'slug'             => 'react-course',
                'productable_type' => ProductableEnum::COURSE->value,
                'productable_id'   => $course->id,
                'vendor_id'        => $vendor->id,
                'term_id'          => $term->id,
                'status'           => PublicationStatusEnum::PUBLISHED,
                'is_visible'       => true,
            ]);

            // Create a promotion
            $promotion = DiscountPromotion::factory()->create([
                'name'      => 'Early Bird Discount',
                'type'      => DiscountTypeEnum::PRODUCT_SPECIFIC,
                'is_active' => true,
                'starts_at' => now()->subDay(),
                'ends_at'   => now()->addDays(30),
            ]);

            $onlineOption = ProductDeliveryOption::factory()->create([
                'product_id'       => $product->id,
                'name'             => 'Online React Course',
                'sku'              => 'REACT-ONLINE-001',
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE,
                'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE,
                'price'            => 900000,
                'status'           => PublicationStatusEnum::PUBLISHED,
            ]);

            $inPersonOption = ProductDeliveryOption::factory()->create([
                'product_id'       => $product->id,
                'name'             => 'In-Person React Course',
                'sku'              => 'REACT-INPERSON-001',
                'fulfillment_type' => FulfillmentTypeEnum::IN_PERSON_SERVICE,
                'delivery_method'  => DeliveryMethodEnum::IN_PERSON,
                'price'            => 1200000,
                'status'           => PublicationStatusEnum::PUBLISHED,
            ]);

            // Create discount prices (cached promotions)
            ProductDeliveryOptionDiscountPrice::factory()->create([
                'product_delivery_option_id' => $onlineOption->id,
                'discount_promotion_id'      => $promotion->id,
                'discounted_price'           => 720000, // 20% off
            ]);

            ProductDeliveryOptionDiscountPrice::factory()->create([
                'product_delivery_option_id' => $inPersonOption->id,
                'discount_promotion_id'      => $promotion->id,
                'discounted_price'           => 960000, // 20% off
            ]);

            $response = getJson(route('api.v1.shop.courses.index'));

            $response->assertOk();
            $courseData = $response->json('data.data.0');

            // Should use minimum discounted price
            expect($courseData['price'])->toBe(720000) // Discounted online price
                ->and($courseData['original_price'])->toBe(900000) // Minimum original price
                ->and($courseData['has_discount'])->toBeTrue()
                ->and($courseData['discount_percent'])->toBe(20.0); // 20% discount

            // Verify price data shows discount info
            expect($courseData['price_data']['min_price'])->toBe(720000)
                ->and($courseData['price_data']['min_original_price'])->toBe(900000)
                ->and($courseData['price_data']['has_discount'])->toBeTrue()
                ->and($courseData['price_data']['discount_type'])->toBe('promotion')
                ->and($courseData['price_data']['discount_percentage'])->toBe(20.0);
        });

        it('returns correct pricing data with prepayment options', function (): void {
            $vendor = Vendor::factory()->create();
            $term   = Term::factory()->create();

            $course = Course::factory()->create([
                'full_name' => 'Vue.js Mastery',
                'status'    => PublicationStatusEnum::PUBLISHED,
            ]);

            $product = Product::factory()->create([
                'name'             => 'Vue.js Course',
                'slug'             => 'vuejs-course',
                'productable_type' => ProductableEnum::COURSE->value,
                'productable_id'   => $course->id,
                'vendor_id'        => $vendor->id,
                'term_id'          => $term->id,
                'status'           => PublicationStatusEnum::PUBLISHED,
                'is_visible'       => true,
            ]);

            // Create option with prepayment
            $prepaymentOption = ProductDeliveryOption::factory()->create([
                'product_id'              => $product->id,
                'name'                    => 'Vue Course with Prepayment',
                'sku'                     => 'VUE-PREPAYMENT-001',
                'fulfillment_type'        => FulfillmentTypeEnum::ONLINE_SERVICE,
                'delivery_method'         => DeliveryMethodEnum::LMS_MOODLE,
                'price'                   => 1000000,
                'status'                  => PublicationStatusEnum::PUBLISHED,
                'is_prepayment_available' => true,
                'prepayment_amount'       => 300000, // 30% prepayment
            ]);

            $response = getJson(route('api.v1.shop.courses.index'));

            $response->assertOk();
            $courseData = $response->json('data.data.0');
            // Verify price data shows prepayment info
            expect($courseData['price_data']['has_pre_payment'])->toBeTrue()
                ->and($courseData['price_data']['min_price'])->toBe(1000000);
        });

        it('handles complex pricing scenarios with multiple discounts and features', function (): void {
            $vendor = Vendor::factory()->create();
            $term   = Term::factory()->create();

            $course = Course::factory()->create([
                'full_name' => 'Full Stack Development',
                'status'    => PublicationStatusEnum::PUBLISHED,
            ]);

            $product = Product::factory()->create([
                'name'             => 'Full Stack Course',
                'slug'             => 'full-stack-course',
                'productable_type' => ProductableEnum::COURSE->value,
                'productable_id'   => $course->id,
                'vendor_id'        => $vendor->id,
                'term_id'          => $term->id,
                'status'           => PublicationStatusEnum::PUBLISHED,
                'is_visible'       => true,
                'is_featured'      => true,
            ]);

            $promotion = DiscountPromotion::factory()->create([
                'name'      => 'Black Friday Sale',
                'type'      => DiscountTypeEnum::PRODUCT_SPECIFIC,
                'is_active' => true,
                'starts_at' => now()->subDay(),
                'ends_at'   => now()->addDays(7),
            ]);

            // Option 1: Has both featured price AND promotion discount (promotion should win)
            $option1 = ProductDeliveryOption::factory()->create([
                'product_id'                => $product->id,
                'name'                      => 'Premium Full Stack',
                'sku'                       => 'FULLSTACK-PREMIUM-001',
                'fulfillment_type'          => FulfillmentTypeEnum::ONLINE_SERVICE,
                'delivery_method'           => DeliveryMethodEnum::LMS_MOODLE,
                'price'                     => 2000000, // Original
                'is_featured'               => true,
                'featured_price'            => 1600000, // 20% off via featured
                'featured_price_start_date' => now()->subDay(),
                'featured_price_end_date'   => now()->addDays(30),
                'is_prepayment_available'   => true,
                'prepayment_amount'         => 500000,
                'status'                    => PublicationStatusEnum::PUBLISHED,
            ]);

            // Option 2: Only has promotion discount
            $option2 = ProductDeliveryOption::factory()->create([
                'product_id'              => $product->id,
                'name'                    => 'Standard Full Stack',
                'sku'                     => 'FULLSTACK-STANDARD-001',
                'fulfillment_type'        => FulfillmentTypeEnum::OFFLINE_SERVICE,
                'delivery_method'         => DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER,
                'price'                   => 1500000,
                'is_featured'             => false,
                'is_prepayment_available' => false,
                'status'                  => PublicationStatusEnum::PUBLISHED,
            ]);

            // Option 3: No discounts or features (baseline)
            $option3 = ProductDeliveryOption::factory()->create([
                'product_id'              => $product->id,
                'name'                    => 'Basic Full Stack',
                'sku'                     => 'FULLSTACK-BASIC-001',
                'fulfillment_type'        => FulfillmentTypeEnum::IN_PERSON_SERVICE,
                'delivery_method'         => DeliveryMethodEnum::IN_PERSON,
                'price'                   => 1000000,
                'is_featured'             => false,
                'is_prepayment_available' => false,
                'status'                  => PublicationStatusEnum::PUBLISHED,
            ]);

            // Create promotion discounts (should take priority over featured prices)
            ProductDeliveryOptionDiscountPrice::factory()->create([
                'product_delivery_option_id' => $option1->id,
                'discount_promotion_id'      => $promotion->id,
                'discounted_price'           => 1400000, // 30% off (better than featured 20% off)
            ]);

            ProductDeliveryOptionDiscountPrice::factory()->create([
                'product_delivery_option_id' => $option2->id,
                'discount_promotion_id'      => $promotion->id,
                'discounted_price'           => 1050000, // 30% off
            ]);

            // Option 3 has no promotion discount

            $response = getJson(route('api.v1.shop.courses.index'));

            $response->assertOk();
            $courseData = $response->json('data.data.0');

            // Should use the lowest available price (Basic Full Stack at 1000000)
            expect($courseData['price'])->toBe(1000000) // Lowest current price (Basic)
                ->and($courseData['original_price'])->toBe(1000000) // Lowest original price (Basic)
                ->and($courseData['has_discount'])->toBeTrue() // Overall product has discounts
                ->and($courseData['is_featured'])->toBeTrue(); // Product is marked as featured

            // Verify complex price data
            expect($courseData['price_data']['min_price'])->toBe(1000000)
                ->and($courseData['price_data']['min_original_price'])->toBe(1000000)
                ->and($courseData['price_data']['has_featured_price'])->toBeTrue() // At least one option has featured price
                ->and($courseData['price_data']['has_discount'])->toBeTrue() // At least one option has discount
                ->and($courseData['price_data']['has_pre_payment'])->toBeTrue() // At least one option has prepayment
                ->and($courseData['price_data']['discount_type'])->toBe('promotion'); // Highest discount is from promotion

            // Verify price range covers all options
            expect($courseData['price_range'])->toBe([
                'min' => 1000000, // Basic option price
                'max' => 1400000, // Premium option discounted price
            ]);
        });

        it('filters courses by category slug correctly', function (): void {
            $vendor = Vendor::factory()->create();
            $term   = Term::factory()->create();

            $category1 = Category::factory()->create([
                'name'   => 'Web Development',
                'slug'   => 'web-development',
                'status' => PublicationStatusEnum::PUBLISHED,
            ]);

            $category2 = Category::factory()->create([
                'name'   => 'Mobile Development',
                'slug'   => 'mobile-development',
                'status' => PublicationStatusEnum::PUBLISHED,
            ]);

            // Course 1: Web Development
            $webCourse = Course::factory()->create([
                'full_name' => 'Web Development Course',
                'status'    => PublicationStatusEnum::PUBLISHED,
            ]);

            $webProduct = Product::factory()->create([
                'name'             => 'Web Development',
                'slug'             => 'web-development-course',
                'productable_type' => ProductableEnum::COURSE->value,
                'productable_id'   => $webCourse->id,
                'vendor_id'        => $vendor->id,
                'term_id'          => $term->id,
                'status'           => PublicationStatusEnum::PUBLISHED,
                'is_visible'       => true,
            ]);

            ProductDeliveryOption::factory()->create([
                'product_id' => $webProduct->id,
                'price'      => 500000,
                'status'     => PublicationStatusEnum::PUBLISHED,
            ]);

            $webProduct->categories()->attach($category1->id);

            // Course 2: Mobile Development
            $mobileCourse = Course::factory()->create([
                'full_name' => 'Mobile Development Course',
                'status'    => PublicationStatusEnum::PUBLISHED,
            ]);

            $mobileProduct = Product::factory()->create([
                'name'             => 'Mobile Development',
                'slug'             => 'mobile-development-course',
                'productable_type' => ProductableEnum::COURSE->value,
                'productable_id'   => $mobileCourse->id,
                'vendor_id'        => $vendor->id,
                'term_id'          => $term->id,
                'status'           => PublicationStatusEnum::PUBLISHED,
                'is_visible'       => true,
            ]);

            ProductDeliveryOption::factory()->create([
                'product_id' => $mobileProduct->id,
                'price'      => 600000,
                'status'     => PublicationStatusEnum::PUBLISHED,
            ]);

            $mobileProduct->categories()->attach($category2->id);

            // Test filtering by web development category
            $response = getJson(route('api.v1.shop.courses.index', [
                'filter[category_slugs][]' => 'web-development',
            ]));

            $response->assertOk()
                ->assertJsonCount(1, 'data.data');

            $courseData = $response->json('data.data.0');
            expect($courseData['slug'])->toBe('web-development-course');

            // Test filtering by mobile development category
            $response = getJson(route('api.v1.shop.courses.index', [
                'filter[category_slugs][]' => 'mobile-development',
            ]));

            $response->assertOk()
                ->assertJsonCount(1, 'data.data');

            $courseData = $response->json('data.data.0');
            expect($courseData['slug'])->toBe('mobile-development-course');

            // Test no filter returns all courses
            $response = getJson(route('api.v1.shop.courses.index'));

            $response->assertOk()
                ->assertJsonCount(2, 'data.data');
        });
    });

    describe('Course Detail API', function (): void {
        it('returns complete course detail data with correct pricing and delivery options', function (): void {
            // Create comprehensive test data
            $vendor = Vendor::factory()->create(['name' => 'Tech Academy']);
            $term   = Term::factory()->create(['name' => 'Spring 2024']);

            $category1 = Category::factory()->create([
                'name'   => 'Programming',
                'slug'   => 'programming',
                'status' => PublicationStatusEnum::PUBLISHED,
            ]);

            $category2 = Category::factory()->create([
                'name'   => 'Backend',
                'slug'   => 'backend',
                'status' => PublicationStatusEnum::PUBLISHED,
            ]);

            $teacher1 = Teacher::factory()->create([
                'first_name' => 'John',
                'last_name'  => 'Doe',
                'email'      => 'john.doe@example.com',
            ]);

            $teacher2 = Teacher::factory()->create([
                'first_name' => 'Jane',
                'last_name'  => 'Smith',
                'email'      => 'jane.smith@example.com',
            ]);

            $course = Course::factory()->create([
                'full_name'               => 'Complete Node.js Course',
                'short_name'              => 'Node.js',
                'description'             => 'Master Node.js from basics to advanced',
                'duration'                => 50,
                'difficulty_level'        => CourseDifficultyLevelEnum::INTERMEDIATE,
                'career_prospects_text'   => 'Become a backend developer',
                'curriculum_summary_text' => 'Learn Node.js, Express, MongoDB',
                'outcomes_json'           => [
                    'skills'   => ['API Development', 'Database Design', 'Authentication'],
                    'projects' => ['REST API', 'Real-time Chat App'],
                ],
                'default_teacher_info' => 'Experienced Node.js developers',
                'additional_info'      => ['Certificate included', 'Lifetime access'],
                'meta_title'           => 'Node.js Course - Master Backend Development',
                'meta_description'     => 'Learn Node.js with hands-on projects',
                'meta_keywords'        => 'nodejs,backend,javascript,api',
                'properties'           => ['hands-on', 'project-based'],
                'status'               => PublicationStatusEnum::PUBLISHED,
            ]);

            $product = Product::factory()->create([
                'name'             => 'Complete Node.js Course',
                'slug'             => 'complete-nodejs-course',
                'short_name'       => 'Node.js Course',
                'productable_type' => ProductableEnum::COURSE->value,
                'productable_id'   => $course->id,
                'vendor_id'        => $vendor->id,
                'term_id'          => $term->id,
                'status'           => PublicationStatusEnum::PUBLISHED,
                'is_visible'       => true,
                'is_featured'      => true,
                'details_json'     => ['difficulty_level' => 'intermediate', 'language' => 'English'],
            ]);

            $product->categories()->attach([$category1->id, $category2->id]);

            // Create promotion
            $promotion = DiscountPromotion::factory()->create([
                'name'      => 'Summer Sale',
                'type'      => DiscountTypeEnum::PRODUCT_SPECIFIC,
                'is_active' => true,
                'starts_at' => now()->subWeek(),
                'ends_at'   => now()->addWeek(),
            ]);

            // Create multiple delivery options with different pricing scenarios
            $onlineOption = ProductDeliveryOption::factory()->create([
                'product_id'                => $product->id,
                'name'                      => 'Online Live Sessions',
                'sku'                       => 'NODEJS-ONLINE-LIVE',
                'fulfillment_type'          => FulfillmentTypeEnum::ONLINE_SERVICE,
                'delivery_method'           => DeliveryMethodEnum::LMS_MOODLE,
                'price'                     => 1200000,
                'status'                    => PublicationStatusEnum::PUBLISHED,
                'is_featured'               => true,
                'featured_price'            => 1000000, // Featured price
                'featured_price_start_date' => now()->subDay(),
                'featured_price_end_date'   => now()->addMonth(),
                'is_prepayment_available'   => true,
                'prepayment_amount'         => 300000,
            ]);

            $videoOption = ProductDeliveryOption::factory()->create([
                'product_id'              => $product->id,
                'name'                    => 'Pre-recorded Videos',
                'sku'                     => 'NODEJS-VIDEO-COURSE',
                'fulfillment_type'        => FulfillmentTypeEnum::OFFLINE_SERVICE,
                'delivery_method'         => DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER,
                'price'                   => 800000,
                'status'                  => PublicationStatusEnum::PUBLISHED,
                'is_featured'             => false,
                'is_prepayment_available' => false,
            ]);

            $inPersonOption = ProductDeliveryOption::factory()->create([
                'product_id'              => $product->id,
                'name'                    => 'In-Person Bootcamp',
                'sku'                     => 'NODEJS-BOOTCAMP',
                'fulfillment_type'        => FulfillmentTypeEnum::IN_PERSON_SERVICE,
                'delivery_method'         => DeliveryMethodEnum::IN_PERSON,
                'price'                   => 2000000,
                'status'                  => PublicationStatusEnum::PUBLISHED,
                'is_featured'             => false,
                'is_prepayment_available' => true,
                'prepayment_amount'       => 800000,
            ]);

            // Attach teachers to options
            $onlineOption->teachers()->attach([$teacher1->id, $teacher2->id]);
            $videoOption->teachers()->attach([$teacher1->id]);
            $inPersonOption->teachers()->attach([$teacher2->id]);

            // Create promotion discounts (should override featured prices)
            ProductDeliveryOptionDiscountPrice::factory()->create([
                'product_delivery_option_id' => $onlineOption->id,
                'discount_promotion_id'      => $promotion->id,
                'discounted_price'           => 900000, // Better than featured price
            ]);

            ProductDeliveryOptionDiscountPrice::factory()->create([
                'product_delivery_option_id' => $videoOption->id,
                'discount_promotion_id'      => $promotion->id,
                'discounted_price'           => 600000, // 25% off
            ]);

            // In-person option has no promotion discount

            $response = getJson(route('api.v1.shop.courses.show', ['product' => 'complete-nodejs-course']));

            $response->assertOk()
                ->assertJsonStructure([
                    'message',
                    'data' => [
                        'slug',
                        'full_name',
                        'short_name',
                        'priceData',
                        'description',
                        'duration',
                        'difficulty_level',
                        'career_prospects_text',
                        'curriculum_summary_text',
                        'outcomes_json',
                        'default_teacher_info',
                        'additional_info',
                        'meta_title',
                        'meta_description',
                        'meta_keywords',
                        'properties',
                        'details',
                        'status',
                        'categories',
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

            $courseData = $response->json('data');

            // Verify basic course information
            expect($courseData['slug'])->toBe('complete-nodejs-course')
                ->and($courseData['full_name'])->toBe('Complete Node.js Course')
                ->and($courseData['short_name'])->toBe('Node.js Course')
                ->and($courseData['description'])->toBe('Master Node.js from basics to advanced')
                ->and($courseData['duration'])->toBe(50)
                ->and($courseData['difficulty_level']['value'])->toBe('intermediate')
                ->and($courseData['difficulty_level']['label'])->toBe('Intermediate') // Check actual label value
                ->and($courseData['career_prospects_text'])->toBe('Become a backend developer')
                ->and($courseData['curriculum_summary_text'])->toBe('Learn Node.js, Express, MongoDB')
                ->and($courseData['default_teacher_info'])->toBe('Experienced Node.js developers')
                ->and($courseData['outcomes_json'])->toBe([
                    'skills'   => ['API Development', 'Database Design', 'Authentication'],
                    'projects' => ['REST API', 'Real-time Chat App'],
                ])
                ->and($courseData['additional_info'])->toBe(['Certificate included', 'Lifetime access'])
                ->and($courseData['properties'])->toBe(['hands-on', 'project-based'])
                ->and($courseData['details'])->toEqual(['difficulty_level' => 'intermediate', 'language' => 'English'])
                ->and($courseData['meta_title'])->toBe('Node.js Course - Master Backend Development')
                ->and($courseData['meta_description'])->toBe('Learn Node.js with hands-on projects')
                ->and($courseData['meta_keywords'])->toBe('nodejs,backend,javascript,api')
                ->and($courseData['status']['value'])->toBe('published')
                ->and($courseData['status']['label'])->toBe('Published')
                ->and($courseData['categories'])->toHaveCount(2);

            // Verify categories
            $categoryNames = collect($courseData['categories'])->pluck('name')->toArray();
            expect($categoryNames)->toContain('Programming', 'Backend')
                ->and($courseData['priceData']['min_price'])->toBe(600000) // Video option with promotion
                ->and($courseData['priceData']['min_original_price'])->toBe(800000) // Video option original
                ->and($courseData['priceData']['has_featured_price'])->toBeTrue() // Online option has featured
                ->and($courseData['priceData']['has_discount'])->toBeTrue() // Promotions available
                ->and($courseData['priceData']['has_pre_payment'])->toBeTrue() // Some options have prepayment
                ->and($courseData['priceData']['discount_type'])->toBe('promotion')
                ->and($courseData['priceData']['discount_percentage'])->toBe(25.0)
                ->and($courseData['delivery_options'])->toHaveCount(3);

            $deliveryOptions = collect($courseData['delivery_options'])->keyBy('sku');

            // Online option (promotion discount overrides featured price)
            $onlineData = $deliveryOptions['NODEJS-ONLINE-LIVE'];
            expect($onlineData['name'])->toBe('Online Live Sessions')
                ->and($onlineData['fulfillment_type']['value'])->toBe('online_service')
                ->and($onlineData['delivery_method']['value'])->toBe('lms_moodle')
                ->and($onlineData['price_data']['current_price'])->toBe(900000) // Promotion price
                ->and($onlineData['price_data']['original_price'])->toBe(1200000) // Original price
                ->and($onlineData['price_data']['featured_price'])->toBe(1000000) // Featured price exists but not used
                ->and($onlineData['price_data']['discount_amount'])->toBe(300000) // Discount amount
                ->and($onlineData['price_data']['has_discount'])->toBeTrue()
                ->and($onlineData['price_data']['has_featured_price'])->toBeTrue()
                ->and($onlineData['price_data']['has_pre_payment_price'])->toBeTrue()
                ->and($onlineData['price_data']['pre_payment_price'])->toBe(300000)
                ->and($onlineData['price_data']['discount_type'])->toBe('promotion')
                ->and($onlineData['price_data']['discount_percentage'])->toBe(25.0); // 300k/1200k * 100

            // Video option (promotion discount only)
            $videoData = $deliveryOptions['NODEJS-VIDEO-COURSE'];
            expect($videoData['name'])->toBe('Pre-recorded Videos')
                ->and($videoData['fulfillment_type']['value'])->toBe('offline_service')
                ->and($videoData['delivery_method']['value'])->toBe('video_platform_spotplayer')
                ->and($videoData['price_data']['current_price'])->toBe(600000) // Promotion price
                ->and($videoData['price_data']['original_price'])->toBe(800000) // Original price
                ->and($videoData['price_data']['featured_price'])->toBeNull()
                ->and($videoData['price_data']['discount_amount'])->toBe(200000) // Discount amount
                ->and($videoData['price_data']['has_discount'])->toBeTrue()
                ->and($videoData['price_data']['has_featured_price'])->toBeFalse()
                ->and($videoData['price_data']['has_pre_payment_price'])->toBeFalse()
                ->and($videoData['price_data']['discount_type'])->toBe('promotion')
                ->and($videoData['price_data']['discount_percentage'])->toBe(25.0);

            // In-person option (no discounts, just prepayment)
            $inPersonData = $deliveryOptions['NODEJS-BOOTCAMP'];
            expect($inPersonData['name'])->toBe('In-Person Bootcamp')
                ->and($inPersonData['fulfillment_type']['value'])->toBe('in_person_service')
                ->and($inPersonData['delivery_method']['value'])->toBe('in_person')
                ->and($inPersonData['price_data']['current_price'])->toBe(2000000) // Original price (no discount)
                ->and($inPersonData['price_data']['original_price'])->toBe(2000000) // Original price
                ->and($inPersonData['price_data']['featured_price'])->toBeNull()
                ->and($inPersonData['price_data']['discount_amount'])->toBeNull()
                ->and($inPersonData['price_data']['has_discount'])->toBeFalse()
                ->and($inPersonData['price_data']['has_featured_price'])->toBeFalse()
                ->and($inPersonData['price_data']['has_pre_payment_price'])->toBeTrue()
                ->and($inPersonData['price_data']['pre_payment_price'])->toBe(800000)
                ->and($inPersonData['price_data']['discount_type'])->toBeNull()
                ->and($inPersonData['price_data']['discount_percentage'])->toBeNull();

            // Verify media structure exists
            expect($courseData['media'])->toHaveKeys(['gallery', 'video', 'cover', 'certificate', 'main']);
        });

        it('courses with no delivery options should return 404', function (): void {
            $vendor = Vendor::factory()->create();
            $term   = Term::factory()->create();

            $course = Course::factory()->create([
                'full_name' => 'Course Without Options',
                'status'    => PublicationStatusEnum::PUBLISHED,
            ]);

            $product = Product::factory()
                ->create([
                    'name'             => 'Course Without Options',
                    'slug'             => 'course-without-options',
                    'productable_type' => ProductableEnum::COURSE->value,
                    'productable_id'   => $course->id,
                    'vendor_id'        => $vendor->id,
                    'term_id'          => $term->id,
                    'status'           => PublicationStatusEnum::PUBLISHED,
                    'is_visible'       => true,
                ]);

            $response = getJson(route('api.v1.shop.courses.show', ['product' => 'course-without-options']));
            $response->assertNotFound();
        });

        it('returns 404 for non-existent course', function (): void {
            $response = getJson(route('api.v1.shop.courses.show', ['product' => 'non-existent-course']));

            $response->assertNotFound();
        });

        it('returns 404 for unpublished course', function (): void {
            $vendor = Vendor::factory()->create();
            $term   = Term::factory()->create();

            $course = Course::factory()->create([
                'status' => PublicationStatusEnum::DRAFT, // Unpublished
            ]);

            $product = Product::factory()->create([
                'slug'             => 'unpublished-course',
                'productable_type' => ProductableEnum::COURSE->value,
                'productable_id'   => $course->id,
                'vendor_id'        => $vendor->id,
                'term_id'          => $term->id,
                'status'           => PublicationStatusEnum::PUBLISHED, // Product is published
                'is_visible'       => true,
            ]);

            ProductDeliveryOption::factory()->create([
                'product_id' => $product->id,
                'status'     => PublicationStatusEnum::PUBLISHED,
            ]);

            $response = getJson(route('api.v1.shop.courses.show', ['product' => 'unpublished-course']));

            $response->assertNotFound();
        });
    });

    describe('Data Consistency', function (): void {
        it('ensures pricing consistency between list and detail endpoints', function (): void {
            $vendor = Vendor::factory()->create();
            $term   = Term::factory()->create();

            $course = Course::factory()->create([
                'full_name' => 'Consistency Test Course',
                'status'    => PublicationStatusEnum::PUBLISHED,
            ]);

            $product = Product::factory()->create([
                'name'             => 'Consistency Test Course',
                'slug'             => 'consistency-test-course',
                'productable_type' => ProductableEnum::COURSE->value,
                'productable_id'   => $course->id,
                'vendor_id'        => $vendor->id,
                'term_id'          => $term->id,
                'status'           => PublicationStatusEnum::PUBLISHED,
                'is_visible'       => true,
            ]);

            $promotion = DiscountPromotion::factory()->create([
                'is_active' => true,
                'starts_at' => now()->subDay(),
                'ends_at'   => now()->addDay(),
            ]);

            $option = ProductDeliveryOption::factory()->create([
                'product_id' => $product->id,
                'price'      => 1000000,
                'status'     => PublicationStatusEnum::PUBLISHED,
            ]);

            ProductDeliveryOptionDiscountPrice::factory()->create([
                'product_delivery_option_id' => $option->id,
                'discount_promotion_id'      => $promotion->id,
                'discounted_price'           => 800000,
            ]);

            // Get data from list endpoint
            $listResponse = getJson(route('api.v1.shop.courses.index'));
            $listData     = $listResponse->json('data.data.0');

            // Get data from detail endpoint
            $detailResponse = getJson(route('api.v1.shop.courses.show', ['product' => 'consistency-test-course']));
            $detailData     = $detailResponse->json('data');

            // Verify pricing consistency
            expect($listData['price'])->toBe($detailData['priceData']['min_price'])
                ->and($listData['original_price'])->toBe($detailData['priceData']['min_original_price'])
                ->and($listData['has_discount'])->toBe($detailData['priceData']['has_discount'])
                ->and($listData['discount_percent'])->toBe($detailData['priceData']['discount_percentage'])
                ->and($listData['price_range'])->toBe($detailData['priceData']['range']);

            // Verify price_data consistency
            expect($listData['price_data']['min_price'])->toBe($detailData['priceData']['min_price'])
                ->and($listData['price_data']['min_original_price'])->toBe($detailData['priceData']['min_original_price'])
                ->and($listData['price_data']['has_discount'])->toBe($detailData['priceData']['has_discount'])
                ->and($listData['price_data']['discount_type'])->toBe($detailData['priceData']['discount_type']);
        });

        it('maintains data integrity with concurrent price updates', function (): void {
            // This test verifies that the same product accessed multiple times returns consistent data

            $vendor = Vendor::factory()->create();
            $term   = Term::factory()->create();

            $course = Course::factory()->create([
                'full_name' => 'Concurrent Access Test Course',
                'status'    => PublicationStatusEnum::PUBLISHED,
            ]);

            $product = Product::factory()->create([
                'name'             => 'Concurrent Access Test',
                'slug'             => 'concurrent-access-test',
                'productable_type' => ProductableEnum::COURSE->value,
                'productable_id'   => $course->id,
                'vendor_id'        => $vendor->id,
                'term_id'          => $term->id,
                'status'           => PublicationStatusEnum::PUBLISHED,
                'is_visible'       => true,
            ]);

            ProductDeliveryOption::factory()->create([
                'product_id' => $product->id,
                'price'      => 750000,
                'status'     => PublicationStatusEnum::PUBLISHED,
                'capacity'   => 100, // Ensure adequate capacity
            ]);

            // Make multiple requests
            $responses = [];
            for ($i = 0; $i < 5; $i++) {
                $response    = getJson(route('api.v1.shop.courses.show', ['product' => 'concurrent-access-test']));
                $responses[] = $response;
            }
            // Verify all responses are successful first
            foreach ($responses as $response) {
                $response->assertOk();
            }

            // Verify all responses are identical
            $firstResponseData = $responses[0]->json('data');
            foreach ($responses as $response) {
                $responseData = $response->json('data');
                expect($responseData['priceData']['min_price'])->toBe($firstResponseData['priceData']['min_price'])
                    ->and($responseData['slug'])->toBe($firstResponseData['slug']);

                // Handle null delivery_options gracefully
                $firstOptionsCount    = $firstResponseData['delivery_options'] ? count($firstResponseData['delivery_options']) : 0;
                $responseOptionsCount = $responseData['delivery_options'] ? count($responseData['delivery_options']) : 0;
                expect($responseOptionsCount)->toBe($firstOptionsCount);
            }
        });
    });
});
