<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\AvailabilityStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Enums\System\MorphTypeEnum;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Seminar;
use App\Models\Term;
use App\Models\Vendor;
use Carbon\Carbon;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

// =============================================================================
// TEST 1: PAST filter on seminars returns past events (event_ended_at < today)
// =============================================================================
it('filters seminars by availability_status=past returning past events', function () {
    $today = Carbon::now()->startOfDay();

    // Arrange: Past seminar (ended yesterday)
    $pastSeminar = Product::factory()
        ->withSeminar(Seminar::factory()->create(['full_name' => 'Past Seminar - API Test']))
        ->create([
            'name'           => 'Past Seminar - API Test',
            'event_start_at' => $today->clone()->subDays(3),
            'event_ended_at' => $today->clone()->subDay(),
        ]);
    ProductDeliveryOption::factory()->for($pastSeminar)->create([
        'price'            => 100_000,
        'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
        'available_from'   => null,
        'available_to'     => null,
        'status'           => PublicationStatusEnum::PUBLISHED->value,
    ]);

    // Distractor: Upcoming seminar (starts tomorrow)
    $upcoming = Product::factory()
        ->withSeminar(Seminar::factory()->create(['full_name' => 'Future Seminar Distractor']))
        ->create([
            'name'           => 'Future Seminar Distractor',
            'event_start_at' => $today->clone()->addDay(),
            'event_ended_at' => $today->clone()->addDays(2),
        ]);
    ProductDeliveryOption::factory()->for($upcoming)->create([
        'price'            => 100_000,
        'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
        'available_from'   => null,
        'available_to'     => null,
        'status'           => PublicationStatusEnum::PUBLISHED->value,
    ]);

    // Act
    $response = getJson(route('api.v1.shop.seminars.index', [
        'filter' => ['availability_status' => AvailabilityStatusEnum::PAST->value],
    ]));

    // Assert
    $response->assertOk();
    $json = $response->json();
    expect($json['data']['total'])->toBe(1);
    expect($json['data']['data'][0]['name'])->toBe('Past Seminar - API Test');
});

// =============================================================================
// TEST 2: UPCOMING filter on seminars returns upcoming events (event_start_at > today)
// =============================================================================
it('filters seminars by availability_status=upcoming returning upcoming events', function () {
    $today = Carbon::now()->startOfDay();

    // Arrange: Upcoming seminar (starts tomorrow)
    $upcomingSeminar = Product::factory()
        ->withSeminar(Seminar::factory()->create(['full_name' => 'Upcoming Seminar - API Test']))
        ->create([
            'name'           => 'Upcoming Seminar - API Test',
            'event_start_at' => $today->clone()->addDay(),
            'event_ended_at' => $today->clone()->addDays(2),
        ]);
    ProductDeliveryOption::factory()->for($upcomingSeminar)->create([
        'price'            => 100_000,
        'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
        'available_from'   => null,
        'available_to'     => null,
        'status'           => PublicationStatusEnum::PUBLISHED->value,
    ]);

    // Distractor: Past seminar
    $past = Product::factory()
        ->withSeminar(Seminar::factory()->create(['full_name' => 'Past Seminar Distractor']))
        ->create([
            'name'           => 'Past Seminar Distractor',
            'event_start_at' => $today->clone()->subDays(3),
            'event_ended_at' => $today->clone()->subDay(),
        ]);
    ProductDeliveryOption::factory()->for($past)->create([
        'price'            => 100_000,
        'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
        'available_from'   => null,
        'available_to'     => null,
        'status'           => PublicationStatusEnum::PUBLISHED->value,
    ]);

    // Act
    $response = getJson(route('api.v1.shop.seminars.index', [
        'filter' => ['availability_status' => AvailabilityStatusEnum::UPCOMING->value],
    ]));

    // Assert
    $response->assertOk();
    $json = $response->json();
    expect($json['data']['total'])->toBe(1);
    expect($json['data']['data'][0]['name'])->toBe('Upcoming Seminar - API Test');
});

// =============================================================================
// TEST 3: ONGOING filter on seminars returns ongoing events (today between start and end)
// =============================================================================
it('filters seminars by availability_status=ongoing returning ongoing events', function () {
    $today = Carbon::now()->startOfDay();

    // Arrange: Ongoing seminar (started yesterday, ends tomorrow)
    $ongoingSeminar = Product::factory()
        ->withSeminar(Seminar::factory()->create(['full_name' => 'Ongoing Seminar - API Test']))
        ->create([
            'name'           => 'Ongoing Seminar - API Test',
            'event_start_at' => $today->clone()->subDay(),
            'event_ended_at' => $today->clone()->addDay(),
        ]);
    ProductDeliveryOption::factory()->for($ongoingSeminar)->create([
        'price'            => 100_000,
        'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
        'available_from'   => null,
        'available_to'     => null,
        'status'           => PublicationStatusEnum::PUBLISHED->value,
    ]);

    // Distractor: Past seminar
    $past = Product::factory()
        ->withSeminar(Seminar::factory()->create(['full_name' => 'Past Seminar Distractor']))
        ->create([
            'name'           => 'Past Seminar Distractor',
            'event_start_at' => $today->clone()->subDays(5),
            'event_ended_at' => $today->clone()->subDays(2),
        ]);
    ProductDeliveryOption::factory()->for($past)->create([
        'price'            => 100_000,
        'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
        'available_from'   => null,
        'available_to'     => null,
        'status'           => PublicationStatusEnum::PUBLISHED->value,
    ]);

    // Distractor: Upcoming seminar
    $future = Product::factory()
        ->withSeminar(Seminar::factory()->create(['full_name' => 'Upcoming Seminar Distractor']))
        ->create([
            'name'           => 'Upcoming Seminar Distractor',
            'event_start_at' => $today->clone()->addDays(2),
            'event_ended_at' => $today->clone()->addDays(4),
        ]);
    ProductDeliveryOption::factory()->for($future)->create([
        'price'            => 100_000,
        'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
        'available_from'   => null,
        'available_to'     => null,
        'status'           => PublicationStatusEnum::PUBLISHED->value,
    ]);

    // Act
    $response = getJson(route('api.v1.shop.seminars.index', [
        'filter' => ['availability_status' => AvailabilityStatusEnum::ONGOING->value],
    ]));

    // Assert
    $response->assertOk();
    $json = $response->json();
    expect($json['data']['total'])->toBe(1);
    expect($json['data']['data'][0]['name'])->toBe('Ongoing Seminar - API Test');
});

// =============================================================================
// TEST 4: Default seminar listing (no filter) excludes past events
// =============================================================================
it('default seminar listing excludes past events via eventNotEnded default', function () {
    $today = Carbon::now()->startOfDay();

    // Arrange: Past seminar (should be EXCLUDED from default listing)
    $past = Product::factory()
        ->withSeminar(Seminar::factory()->create(['full_name' => 'Past Seminar Excluded']))
        ->create([
            'name'           => 'Past Seminar Excluded',
            'event_start_at' => $today->clone()->subDays(5),
            'event_ended_at' => $today->clone()->subDay(),
        ]);
    ProductDeliveryOption::factory()->for($past)->create([
        'price'            => 100_000,
        'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
        'available_from'   => null,
        'available_to'     => null,
        'status'           => PublicationStatusEnum::PUBLISHED->value,
    ]);

    // Upcoming seminar (should be INCLUDED)
    $upcoming = Product::factory()
        ->withSeminar(Seminar::factory()->create(['full_name' => 'Upcoming Seminar Included']))
        ->create([
            'name'           => 'Upcoming Seminar Included',
            'event_start_at' => $today->clone()->addDay(),
            'event_ended_at' => $today->clone()->addDays(3),
        ]);
    ProductDeliveryOption::factory()->for($upcoming)->create([
        'price'            => 100_000,
        'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
        'available_from'   => null,
        'available_to'     => null,
        'status'           => PublicationStatusEnum::PUBLISHED->value,
    ]);

    // Ongoing seminar (should be INCLUDED)
    $ongoing = Product::factory()
        ->withSeminar(Seminar::factory()->create(['full_name' => 'Ongoing Seminar Included']))
        ->create([
            'name'           => 'Ongoing Seminar Included',
            'event_start_at' => $today->clone()->subDay(),
            'event_ended_at' => $today->clone()->addDay(),
        ]);
    ProductDeliveryOption::factory()->for($ongoing)->create([
        'price'            => 100_000,
        'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
        'available_from'   => null,
        'available_to'     => null,
        'status'           => PublicationStatusEnum::PUBLISHED->value,
    ]);

    // Act: Default listing - no filter
    $response = getJson(route('api.v1.shop.seminars.index'));

    // Assert
    $response->assertOk();
    $json = $response->json();

    // Should have 2 results (upcoming + ongoing), NOT past
    $names = array_map(fn ($item) => $item['name'], $json['data']['data']);

    expect($json['data']['total'])->toBe(2);
    expect($names)->toContain('Upcoming Seminar Included')
        ->and($names)->toContain('Ongoing Seminar Included')
        ->and($names)->not->toContain('Past Seminar Excluded');
});

// =============================================================================
// TEST 5: PAST filter works for courses with null event dates but past available_to (fallback)
// =============================================================================
it('filters courses by availability_status=past via available_to fallback', function () {
    $today = Carbon::now()->startOfDay();

    // Arrange: Course with null event dates, PDO available_to in the past (fallback to PAST)
    $course = Course::factory()->create([
        'full_name' => 'Past Course via Fallback',
        'status'    => PublicationStatusEnum::PUBLISHED,
    ]);
    $pastCourseProduct = Product::factory()->create([
        'vendor_id'        => Vendor::factory(),
        'term_id'          => Term::factory(),
        'productable_id'   => $course->id,
        'productable_type' => MorphTypeEnum::COURSE->value,
        'name'             => 'Past Course via Fallback',
        'event_start_at'   => null,
        'event_ended_at'   => null,
    ]);
    ProductDeliveryOption::factory()->for($pastCourseProduct)->create([
        'price'            => 100_000,
        'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
        'available_from'   => $today->clone()->subDays(10),
        'available_to'     => $today->clone()->subDay(),
        'status'           => PublicationStatusEnum::PUBLISHED->value,
    ]);

    // Distractor: Course with null event dates, PDO available_from in the future
    $course2 = Course::factory()->create([
        'full_name' => 'Future Course Distractor',
        'status'    => PublicationStatusEnum::PUBLISHED,
    ]);
    $futureProduct = Product::factory()->create([
        'vendor_id'        => Vendor::factory(),
        'term_id'          => Term::factory(),
        'productable_id'   => $course2->id,
        'productable_type' => MorphTypeEnum::COURSE->value,
        'name'             => 'Future Course Distractor',
        'event_start_at'   => null,
        'event_ended_at'   => null,
    ]);
    ProductDeliveryOption::factory()->for($futureProduct)->create([
        'price'            => 100_000,
        'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
        'available_from'   => $today->clone()->addDay(),
        'available_to'     => $today->clone()->addDays(10),
        'status'           => PublicationStatusEnum::PUBLISHED->value,
    ]);

    // Act
    $response = getJson(route('api.v1.shop.courses.index', [
        'filter' => ['availability_status' => AvailabilityStatusEnum::PAST->value],
    ]));

    // Assert
    $response->assertOk();
    $json = $response->json();
    expect($json['data']['total'])->toBe(1);
    expect($json['data']['data'][0]['name'])->toBe('Past Course via Fallback');
});

// =============================================================================
// TEST 6: PAST filter works for digital assets with null event dates (null fallback)
// =============================================================================
it('filters digital-assets by availability_status=past via available_to fallback', function () {
    $today = Carbon::now()->startOfDay();

    // Arrange: Digital asset with null event dates, PDO available_to in the past (fallback to PAST)
    $asset = DigitalAsset::factory()->create([
        'full_name' => 'Past Digital Asset via Fallback',
        'status'    => PublicationStatusEnum::PUBLISHED,
    ]);
    $pastAssetProduct = Product::factory()->create([
        'vendor_id'        => Vendor::factory(),
        'term_id'          => Term::factory(),
        'productable_id'   => $asset->id,
        'productable_type' => MorphTypeEnum::DIGITAL_ASSET->value,
        'name'             => 'Past Digital Asset via Fallback',
        'event_start_at'   => null,
        'event_ended_at'   => null,
    ]);
    ProductDeliveryOption::factory()->for($pastAssetProduct)->create([
        'price'            => 100_000,
        'fulfillment_type' => FulfillmentTypeEnum::DIGITAL->value,
        'delivery_method'  => DeliveryMethodEnum::DIRECT_DOWNLOAD,
        'available_from'   => $today->clone()->subDays(5),
        'available_to'     => $today->clone()->subDay(),
        'status'           => PublicationStatusEnum::PUBLISHED->value,
    ]);

    // Distractor: Digital asset with null event dates, PDO availability in the future
    $asset2 = DigitalAsset::factory()->create([
        'full_name' => 'Future Asset Distractor',
        'status'    => PublicationStatusEnum::PUBLISHED,
    ]);
    $futureProduct = Product::factory()->create([
        'vendor_id'        => Vendor::factory(),
        'term_id'          => Term::factory(),
        'productable_id'   => $asset2->id,
        'productable_type' => MorphTypeEnum::DIGITAL_ASSET->value,
        'name'             => 'Future Asset Distractor',
        'event_start_at'   => null,
        'event_ended_at'   => null,
    ]);
    ProductDeliveryOption::factory()->for($futureProduct)->create([
        'price'            => 100_000,
        'fulfillment_type' => FulfillmentTypeEnum::DIGITAL->value,
        'delivery_method'  => DeliveryMethodEnum::DIRECT_DOWNLOAD,
        'available_from'   => $today->clone()->addDay(),
        'available_to'     => $today->clone()->addDays(10),
        'status'           => PublicationStatusEnum::PUBLISHED->value,
    ]);

    // Act
    $response = getJson(route('api.v1.shop.digital-assets.index', [
        'filter' => ['availability_status' => AvailabilityStatusEnum::PAST->value],
    ]));

    // Assert
    $response->assertOk();
    $json = $response->json();
    expect($json['data']['total'])->toBe(1);
    expect($json['data']['data'][0]['name'])->toBe('Past Digital Asset via Fallback');
});

// =============================================================================
// TEST 7: Past seminar with SpotPlayer recording PDO (available_to=null) → checkout succeeds
// =============================================================================
it('allows checkout of past seminar with SpotPlayer recording delivery option', function () {
    uses(Tests\Support\Traits\AuthTestTrait::class);
    $this->customer();

    $today = Carbon::now()->startOfDay();

    // Arrange: Past seminar with SpotPlayer recording (event ended, but recording always available)
    $seminar = Seminar::factory()->create([
        'full_name' => 'Past Seminar Recording',
        'status'    => PublicationStatusEnum::PUBLISHED,
    ]);
    $pastSeminar = Product::factory()->create([
        'vendor_id'        => Vendor::factory(),
        'term_id'          => Term::factory(),
        'productable_id'   => $seminar->id,
        'productable_type' => MorphTypeEnum::SEMINAR->value,
        'name'             => 'Past Seminar Recording',
        'event_start_at'   => $today->clone()->subDays(10),
        'event_ended_at'   => $today->clone()->subDay(),
        'status'           => PublicationStatusEnum::PUBLISHED,
        'is_visible'       => true,
    ]);

    $pdo = ProductDeliveryOption::create([
        'product_id'       => $pastSeminar->id,
        'name'             => 'SpotPlayer Recording',
        'sku'              => 'SPOT-TEST-SKU-'.uniqid(),
        'fulfillment_type' => FulfillmentTypeEnum::OFFLINE_SERVICE,
        'delivery_method'  => DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER,
        'price'            => 0,
        'capacity'         => null,
        'status'           => PublicationStatusEnum::PUBLISHED,
        'details_json'     => ['spot_id' => 'SPOT-TEST-123', 'updated_at' => null],
    ]);

    // Act 1: Add to cart
    $cartResponse = postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $pdo->uuid,
        'quantity'                     => 1,
    ]);
    $cartResponse->assertOk();

    // Act 2: Checkout (free order, no payment_method needed)
    $checkoutResponse = postJson(route('api.v1.shop.checkout'));
    $checkoutResponse->assertCreated();

    // Assert: Checkout response contains order data
    $json = $checkoutResponse->json();
    expect($json['data']['order'])->not->toBeNull()
        ->and($json['data']['order']['grand_total'])->toBe(0);
});
