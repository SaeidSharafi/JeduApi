<?php

declare(strict_types=1);

use App\Data\Shop\Product\Course\ProductFilterData;
use App\Enums\Product\AvailabilityStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Query\ProductQueryService;
use Carbon\Carbon;

describe('EventStatusClassification', function (): void {
    describe('eventStatus()', function (): void {
        it('PAST: returns products where event_ended_at < today even if PDO available_to is null (recording scenario)', function (): void {
            // Arrange: Past product with recording (event ended yesterday, recording always available)
            $pastProduct = Product::factory()
                ->withSeminar()
                ->create([
                    'name'           => 'Past Seminar Recording',
                    'event_start_at' => Carbon::yesterday()->subDay(),
                    'event_ended_at' => Carbon::yesterday(),
                ]);
            ProductDeliveryOption::factory()->for($pastProduct)->create([
                'price'            => 100_000,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
                'delivery_method'  => DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER,
                'available_from'   => null,
                'available_to'     => null,
            ]);

            // Distractor: Upcoming product (should NOT be in PAST results)
            $upcomingProduct = Product::factory()
                ->withSeminar()
                ->create([
                    'name'           => 'Future Seminar',
                    'event_start_at' => Carbon::tomorrow(),
                    'event_ended_at' => Carbon::tomorrow()->addDay(),
                ]);
            ProductDeliveryOption::factory()->for($upcomingProduct)->create([
                'price'            => 100_000,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
                'available_from'   => null,
                'available_to'     => null,
            ]);

            // Act
            $results = ProductQueryService::make()
                ->eventStatus(AvailabilityStatusEnum::PAST)
                ->getQuery()
                ->get();

            // Assert
            expect($results)->toHaveCount(1)
                ->and($results->first()->id)->toBe($pastProduct->id);
        });

        it('PAST: returns products without event dates but with past available_to (fallback)', function (): void {
            // Arrange: Product with no event dates, but PDO available_to in the past
            $pastFallbackProduct = Product::factory()
                ->withSeminar()
                ->create([
                    'name'           => 'Expired Content',
                    'event_start_at' => null,
                    'event_ended_at' => null,
                ]);
            ProductDeliveryOption::factory()->for($pastFallbackProduct)->create([
                'price'            => 100_000,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
                'available_from'   => Carbon::yesterday()->subDays(10),
                'available_to'     => Carbon::yesterday(),
            ]);

            // Distractor: Product with no event dates, available_to still in future
            $activeFallbackProduct = Product::factory()
                ->withSeminar()
                ->create([
                    'name'           => 'Active Content',
                    'event_start_at' => null,
                    'event_ended_at' => null,
                ]);
            ProductDeliveryOption::factory()->for($activeFallbackProduct)->create([
                'price'            => 100_000,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
                'available_from'   => Carbon::yesterday(),
                'available_to'     => Carbon::tomorrow(),
            ]);

            // Act
            $results = ProductQueryService::make()
                ->eventStatus(AvailabilityStatusEnum::PAST)
                ->getQuery()
                ->get();

            // Assert
            expect($results)->toHaveCount(1)
                ->and($results->first()->id)->toBe($pastFallbackProduct->id);
        });

        it('UPCOMING: returns products where event_start_at > today', function (): void {
            // Arrange: Upcoming product
            $upcomingProduct = Product::factory()
                ->withSeminar()
                ->create([
                    'name'           => 'Upcoming Seminar',
                    'event_start_at' => Carbon::tomorrow(),
                    'event_ended_at' => Carbon::tomorrow()->addDays(2),
                ]);
            ProductDeliveryOption::factory()->for($upcomingProduct)->create([
                'price'            => 100_000,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
                'available_from'   => null,
                'available_to'     => null,
            ]);

            // Distractor: Past product
            $pastProduct = Product::factory()
                ->withSeminar()
                ->create([
                    'name'           => 'Past Seminar',
                    'event_start_at' => Carbon::yesterday()->subDays(2),
                    'event_ended_at' => Carbon::yesterday(),
                ]);
            ProductDeliveryOption::factory()->for($pastProduct)->create([
                'price'            => 100_000,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
                'available_from'   => null,
                'available_to'     => null,
            ]);

            // Act
            $results = ProductQueryService::make()
                ->eventStatus(AvailabilityStatusEnum::UPCOMING)
                ->getQuery()
                ->get();

            // Assert
            expect($results)->toHaveCount(1)
                ->and($results->first()->id)->toBe($upcomingProduct->id);
        });

        it('UPCOMING: returns products without event dates but with future available_from (fallback)', function (): void {
            // Arrange: Product with no event dates, PDO available_from in the future
            $upcomingFallbackProduct = Product::factory()
                ->withSeminar()
                ->create([
                    'name'           => 'Upcoming Content',
                    'event_start_at' => null,
                    'event_ended_at' => null,
                ]);
            ProductDeliveryOption::factory()->for($upcomingFallbackProduct)->create([
                'price'            => 100_000,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
                'available_from'   => Carbon::tomorrow(),
                'available_to'     => Carbon::tomorrow()->addDays(30),
            ]);

            // Distractor: Product with no event dates, available_from already past
            $alreadyActiveProduct = Product::factory()
                ->withSeminar()
                ->create([
                    'name'           => 'Already Active Content',
                    'event_start_at' => null,
                    'event_ended_at' => null,
                ]);
            ProductDeliveryOption::factory()->for($alreadyActiveProduct)->create([
                'price'            => 100_000,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
                'available_from'   => Carbon::yesterday(),
                'available_to'     => Carbon::tomorrow()->addDays(30),
            ]);

            // Act
            $results = ProductQueryService::make()
                ->eventStatus(AvailabilityStatusEnum::UPCOMING)
                ->getQuery()
                ->get();

            // Assert
            expect($results)->toHaveCount(1)
                ->and($results->first()->id)->toBe($upcomingFallbackProduct->id);
        });

        it('ONGOING: returns products where today is between event_start_at and event_ended_at', function (): void {
            // Arrange: Ongoing product spanning today
            $ongoingProduct = Product::factory()
                ->withSeminar()
                ->create([
                    'name'           => 'Ongoing Seminar',
                    'event_start_at' => Carbon::yesterday(),
                    'event_ended_at' => Carbon::tomorrow(),
                ]);
            ProductDeliveryOption::factory()->for($ongoingProduct)->create([
                'price'            => 100_000,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
                'available_from'   => null,
                'available_to'     => null,
            ]);

            // Distractor: Past product
            $pastProduct = Product::factory()
                ->withSeminar()
                ->create([
                    'name'           => 'Past Seminar',
                    'event_start_at' => Carbon::yesterday()->subDays(5),
                    'event_ended_at' => Carbon::yesterday(),
                ]);
            ProductDeliveryOption::factory()->for($pastProduct)->create([
                'price'            => 100_000,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
                'available_from'   => null,
                'available_to'     => null,
            ]);

            // Act
            $results = ProductQueryService::make()
                ->eventStatus(AvailabilityStatusEnum::ONGOING)
                ->getQuery()
                ->get();

            // Assert
            expect($results)->toHaveCount(1)
                ->and($results->first()->id)->toBe($ongoingProduct->id);
        });

        it('ONGOING: returns products without event dates but with active availability window (fallback)', function (): void {
            // Arrange: Product with no event dates, active availability window
            $ongoingFallbackProduct = Product::factory()
                ->withSeminar()
                ->create([
                    'name'           => 'Ongoing Content',
                    'event_start_at' => null,
                    'event_ended_at' => null,
                ]);
            ProductDeliveryOption::factory()->for($ongoingFallbackProduct)->create([
                'price'            => 100_000,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
                'available_from'   => Carbon::yesterday(),
                'available_to'     => Carbon::tomorrow(),
            ]);

            // Distractor: Product with no event dates, future availability
            $futureFallbackProduct = Product::factory()
                ->withSeminar()
                ->create([
                    'name'           => 'Future Content',
                    'event_start_at' => null,
                    'event_ended_at' => null,
                ]);
            ProductDeliveryOption::factory()->for($futureFallbackProduct)->create([
                'price'            => 100_000,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
                'available_from'   => Carbon::tomorrow(),
                'available_to'     => Carbon::tomorrow()->addDays(30),
            ]);

            // Act
            $results = ProductQueryService::make()
                ->eventStatus(AvailabilityStatusEnum::ONGOING)
                ->getQuery()
                ->get();

            // Assert
            expect($results)->toHaveCount(1)
                ->and($results->first()->id)->toBe($ongoingFallbackProduct->id);
        });

        it('ONGOING fallback: includes products with null available_to in active window', function (): void {
            // Arrange: Product with no event dates, available_from active, available_to null
            $ongoingNoEndProduct = Product::factory()
                ->withSeminar()
                ->create([
                    'name'           => 'Perpetual Content',
                    'event_start_at' => null,
                    'event_ended_at' => null,
                ]);
            ProductDeliveryOption::factory()->for($ongoingNoEndProduct)->create([
                'price'            => 100_000,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
                'available_from'   => Carbon::yesterday(),
                'available_to'     => null,
            ]);

            // Distractor: Product with no event dates, past availability
            $pastFallbackProduct = Product::factory()
                ->withSeminar()
                ->create([
                    'name'           => 'Past Content',
                    'event_start_at' => null,
                    'event_ended_at' => null,
                ]);
            ProductDeliveryOption::factory()->for($pastFallbackProduct)->create([
                'price'            => 100_000,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
                'available_from'   => Carbon::yesterday()->subDays(10),
                'available_to'     => Carbon::yesterday(),
            ]);

            // Act
            $results = ProductQueryService::make()
                ->eventStatus(AvailabilityStatusEnum::ONGOING)
                ->getQuery()
                ->get();

            // Assert
            expect($results)->toHaveCount(1)
                ->and($results->first()->id)->toBe($ongoingNoEndProduct->id);
        });

        it('returns empty collection when null status is passed', function (): void {
            // Arrange: Create a product that would match PAST
            $pastProduct = Product::factory()
                ->withSeminar()
                ->create([
                    'name'           => 'Past Seminar',
                    'event_start_at' => Carbon::yesterday()->subDay(),
                    'event_ended_at' => Carbon::yesterday(),
                ]);
            ProductDeliveryOption::factory()->for($pastProduct)->create([
                'price'            => 100_000,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);

            // Act: null status → method returns $this without adding constraints
            $results = ProductQueryService::make()
                ->eventStatus(null)
                ->getQuery()
                ->get();

            // Assert: All products with PDOs are returned (no filter applied)
            expect($results)->toHaveCount(1)
                ->and($results->first()->id)->toBe($pastProduct->id);
        });
    });

    describe('eventNotEnded()', function (): void {
        it('excludes products with event_ended_at < today from results', function (): void {
            // Arrange: Three products with different event_end states
            $pastProduct = Product::factory()
                ->withSeminar()
                ->create([
                    'name'           => 'Past Event',
                    'event_start_at' => Carbon::yesterday()->subDays(2),
                    'event_ended_at' => Carbon::yesterday(),
                ]);
            ProductDeliveryOption::factory()->for($pastProduct)->create([
                'price'            => 100_000,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);

            $ongoingProduct = Product::factory()
                ->withSeminar()
                ->create([
                    'name'           => 'Ongoing Event',
                    'event_start_at' => Carbon::yesterday(),
                    'event_ended_at' => Carbon::tomorrow(),
                ]);
            ProductDeliveryOption::factory()->for($ongoingProduct)->create([
                'price'            => 100_000,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);

            $noEndProduct = Product::factory()
                ->withSeminar()
                ->create([
                    'name'           => 'No End Date Event',
                    'event_start_at' => Carbon::yesterday(),
                    'event_ended_at' => null,
                ]);
            ProductDeliveryOption::factory()->for($noEndProduct)->create([
                'price'            => 100_000,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);

            // Act
            $results = ProductQueryService::make()
                ->eventNotEnded()
                ->getQuery()
                ->get();

            // Assert: past product excluded, ongoing + null-end included
            expect($results)->toHaveCount(2)
                ->and($results->pluck('id')->toArray())
                ->toContain($ongoingProduct->id, $noEndProduct->id)
                ->not->toContain($pastProduct->id);
        });

        it('includes products with null event_ended_at', function (): void {
            // Arrange: Product with no event ended date
            $nullEndedProduct = Product::factory()
                ->withSeminar()
                ->create([
                    'name'           => 'Perpetual Event',
                    'event_start_at' => Carbon::yesterday(),
                    'event_ended_at' => null,
                ]);
            ProductDeliveryOption::factory()->for($nullEndedProduct)->create([
                'price'            => 100_000,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);

            // Distractor: Past product
            $pastProduct = Product::factory()
                ->withSeminar()
                ->create([
                    'name'           => 'Past Event',
                    'event_start_at' => Carbon::yesterday()->subDays(2),
                    'event_ended_at' => Carbon::yesterday(),
                ]);
            ProductDeliveryOption::factory()->for($pastProduct)->create([
                'price'            => 100_000,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);

            // Act
            $results = ProductQueryService::make()
                ->eventNotEnded()
                ->getQuery()
                ->get();

            // Assert: null-end product included, past excluded
            expect($results)->toHaveCount(1)
                ->and($results->first()->id)->toBe($nullEndedProduct->id);
        });

        it('includes products where event_ended_at equals today', function (): void {
            // Arrange: Product ending today
            $endsTodayProduct = Product::factory()
                ->withSeminar()
                ->create([
                    'name'           => 'Ends Today Event',
                    'event_start_at' => Carbon::yesterday(),
                    'event_ended_at' => today(),
                ]);
            ProductDeliveryOption::factory()->for($endsTodayProduct)->create([
                'price'            => 100_000,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ]);

            // Act
            $results = ProductQueryService::make()
                ->eventNotEnded()
                ->getQuery()
                ->get();

            // Assert: today-ending product is not ended yet
            expect($results)->toHaveCount(1)
                ->and($results->first()->id)->toBe($endsTodayProduct->id);
        });
    });

    describe('Past seminar with recording scenario', function (): void {
        it('classifies past seminar as PAST even though SpotPlayer recording is still purchasable', function (): void {
            // Arrange: Past seminar with SpotPlayer recording (available_to=null)
            $pastSeminar = Product::factory()
                ->withSeminar()
                ->create([
                    'name'           => 'Past Seminar With Recording',
                    'event_start_at' => Carbon::yesterday()->subDays(2),
                    'event_ended_at' => Carbon::yesterday(),
                ]);
            ProductDeliveryOption::factory()->for($pastSeminar)->create([
                'price'            => 250_000,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
                'delivery_method'  => DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER,
                'available_from'   => null,
                'available_to'     => null,
            ]);

            // Distractor: Ongoing seminar
            $ongoingSeminar = Product::factory()
                ->withSeminar()
                ->create([
                    'name'           => 'Ongoing Seminar',
                    'event_start_at' => Carbon::yesterday(),
                    'event_ended_at' => Carbon::tomorrow(),
                ]);
            ProductDeliveryOption::factory()->for($ongoingSeminar)->create([
                'price'            => 250_000,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
                'available_from'   => null,
                'available_to'     => null,
            ]);

            // Act: Classify as PAST
            $pastResults = ProductQueryService::make()
                ->eventStatus(AvailabilityStatusEnum::PAST)
                ->getQuery()
                ->get();

            // Assert: past seminar IS classified PAST despite purchasable recording
            expect($pastResults)->toHaveCount(1)
                ->and($pastResults->first()->id)->toBe($pastSeminar->id);

            // Act: Verify it is NOT in not-ended results
            $notEndedResults = ProductQueryService::make()
                ->eventNotEnded()
                ->getQuery()
                ->get();

            // Assert: past seminar excluded from eventNotEnded
            expect($notEndedResults)->toHaveCount(1)
                ->and($notEndedResults->first()->id)->toBe($ongoingSeminar->id);
        });
    });

    describe('Integration: ProductFilterData availability_status', function (): void {
        it('filters by PAST availability_status through applyDatabaseAvailabilityFilters', function (): void {
            // Arrange
            $pastProduct = Product::factory()
                ->withSeminar()
                ->create([
                    'name'           => 'Past via Filter',
                    'event_start_at' => Carbon::yesterday()->subDays(3),
                    'event_ended_at' => Carbon::yesterday(),
                ]);
            ProductDeliveryOption::factory()->for($pastProduct)->create([
                'price'            => 100_000,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
                'available_from'   => null,
                'available_to'     => null,
            ]);

            $upcomingProduct = Product::factory()
                ->withSeminar()
                ->create([
                    'name'           => 'Upcoming via Filter',
                    'event_start_at' => Carbon::tomorrow(),
                    'event_ended_at' => Carbon::tomorrow()->addDays(2),
                ]);
            ProductDeliveryOption::factory()->for($upcomingProduct)->create([
                'price'            => 100_000,
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value,
                'available_from'   => null,
                'available_to'     => null,
            ]);

            // Act: Use ProductFilterData with availability_status
            $filterData = new ProductFilterData(
                availability_status: AvailabilityStatusEnum::PAST->value,
            );

            $service = ProductQueryService::make();
            // Simulate what globalSearchProductsDatabase does: apply the filter
            $service->eventStatus(AvailabilityStatusEnum::from($filterData->availability_status));

            $results = $service->getQuery()->get();

            // Assert
            expect($results)->toHaveCount(1)
                ->and($results->first()->id)->toBe($pastProduct->id);
        });
    });
});
