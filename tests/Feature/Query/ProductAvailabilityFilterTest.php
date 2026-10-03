<?php

declare(strict_types=1);

namespace Tests\Integration\Query;

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\AvailabilityStatusEnum;
use App\Enums\TermStatusEnum;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Term;
use App\Query\ProductAvailabilityFilter;
use Carbon\Carbon;

describe('denormalized availability path', function (): void {
    beforeEach(function (): void {
        config()->set('products.availability.use_denormalized', true);
    });

    it('keeps only published and visible products', function (): void {
        $visible   = Product::factory()->create();
        $draft     = Product::factory()->create(['status' => PublicationStatusEnum::DRAFT->value]);
        $invisible = Product::factory()->create(['is_visible' => false]);

        $query = ProductAvailabilityFilter::applyPublishedAndVisible(Product::query());

        expect($query->pluck('id'))->toContain($visible->id)
            ->not->toContain($draft->id)
            ->not->toContain($invisible->id);
    });

    it('keeps only products flagged as having a published delivery option', function (): void {
        $hasOption = Product::factory()->create(['has_published_delivery_option' => true]);
        $noOption  = Product::factory()->create(['has_published_delivery_option' => false]);

        $query = ProductAvailabilityFilter::applyHasPublishedDeliveryOption(Product::query());

        expect($query->pluck('id'))->toContain($hasOption->id)
            ->not->toContain($noOption->id);
    });

    it('keeps only products with a published productable', function (): void {
        $published = Product::factory()->create(['productable_status' => PublicationStatusEnum::PUBLISHED->value]);
        $draft     = Product::factory()->create(['productable_status' => PublicationStatusEnum::DRAFT->value]);

        $query = ProductAvailabilityFilter::applyPublishedProductable(Product::query());

        expect($query->pluck('id'))->toContain($published->id)
            ->not->toContain($draft->id);
    });

    it('keeps only products with an active term snapshot', function (): void {
        $active   = Product::factory()->create(['is_term_active' => true]);
        $inactive = Product::factory()->create(['is_term_active' => false]);

        $query = ProductAvailabilityFilter::applyActiveTerm(Product::query());

        expect($query->pluck('id'))->toContain($active->id)
            ->not->toContain($inactive->id);
    });

    it('includes products with open-ended windows in applyAvailableNow', function (): void {
        $openEnded = Product::factory()->create();

        $query = ProductAvailabilityFilter::applyAvailableNow(Product::query());

        expect($query->pluck('id'))->toContain($openEnded->id);
    });

    it('excludes products whose registration window has not started or has ended', function (): void {
        $futureRegistration = Product::factory()->create(['earliest_registration_start' => now()->addDays(2)]);
        $endedRegistration  = Product::factory()->create(['latest_registration_end' => now()->subDays(2)]);

        $query = ProductAvailabilityFilter::applyAvailableNow(Product::query());

        expect($query->pluck('id'))
            ->not->toContain($futureRegistration->id)
            ->not->toContain($endedRegistration->id);
    });

    it('excludes products whose availability window has not started or has ended', function (): void {
        $futureAvailability = Product::factory()->create(['earliest_availability_start' => now()->addDays(2)]);
        $endedAvailability  = Product::factory()->create(['latest_availability_end' => now()->subDays(2)]);

        $query = ProductAvailabilityFilter::applyAvailableNow(Product::query());

        expect($query->pluck('id'))
            ->not->toContain($futureAvailability->id)
            ->not->toContain($endedAvailability->id);
    });

    it('includes products with an open registration and availability window', function (): void {
        $open = Product::factory()->create([
            'earliest_registration_start' => now()->subDays(5),
            'latest_registration_end'     => now()->addDays(5),
            'earliest_availability_start' => now()->subDays(5),
            'latest_availability_end'     => now()->addDays(5),
        ]);

        $query = ProductAvailabilityFilter::applyAvailableNow(Product::query());

        expect($query->pluck('id'))->toContain($open->id);
    });

    it('includes only products whose content is available now', function (): void {
        $openEnded = Product::factory()->create();
        $open      = Product::factory()->create([
            'earliest_availability_start' => now()->subDays(5),
            'latest_availability_end'     => now()->addDays(5),
        ]);
        $future = Product::factory()->create(['earliest_availability_start' => now()->addDays(2)]);
        $ended  = Product::factory()->create(['latest_availability_end' => now()->subDays(2)]);

        $query = ProductAvailabilityFilter::applyContentAvailableNow(Product::query());

        expect($query->pluck('id'))->toContain($openEnded->id)
            ->toContain($open->id)
            ->not->toContain($future->id)
            ->not->toContain($ended->id);
    });

    it('returns the query unchanged for the registration window when both bounds are null', function (): void {
        $query     = Product::query();
        $sqlBefore = $query->toSql();

        $result = ProductAvailabilityFilter::applyRegistrationWindow($query, null, null);

        expect($result)->toBe($query)
            ->and($result->toSql())->toBe($sqlBefore);
    });

    it('excludes products whose registration ends before the from bound', function (): void {
        $endedBefore = Product::factory()->create(['latest_registration_end' => now()->subDays(2)]);
        $open        = Product::factory()->create();
        $active      = Product::factory()->create(['latest_registration_end' => now()->addDays(2)]);

        $query = ProductAvailabilityFilter::applyRegistrationWindow(Product::query(), Carbon::now(), null);

        expect($query->pluck('id'))->toContain($open->id)
            ->toContain($active->id)
            ->not->toContain($endedBefore->id);
    });

    it('excludes products whose registration starts after the to bound', function (): void {
        $startsAfter = Product::factory()->create(['earliest_registration_start' => now()->addDays(2)]);
        $open        = Product::factory()->create();
        $active      = Product::factory()->create(['earliest_registration_start' => now()->subDays(2)]);

        $query = ProductAvailabilityFilter::applyRegistrationWindow(Product::query(), null, Carbon::now());

        expect($query->pluck('id'))->toContain($open->id)
            ->toContain($active->id)
            ->not->toContain($startsAfter->id);
    });

    it('returns the query unchanged for the availability window when both bounds are null', function (): void {
        $query     = Product::query();
        $sqlBefore = $query->toSql();

        $result = ProductAvailabilityFilter::applyAvailabilityWindow($query, null, null);

        expect($result)->toBe($query)
            ->and($result->toSql())->toBe($sqlBefore);
    });

    it('excludes products whose availability ends before the from bound', function (): void {
        $endedBefore = Product::factory()->create(['latest_availability_end' => now()->subDays(2)]);
        $open        = Product::factory()->create();
        $active      = Product::factory()->create(['latest_availability_end' => now()->addDays(2)]);

        $query = ProductAvailabilityFilter::applyAvailabilityWindow(Product::query(), Carbon::now(), null);

        expect($query->pluck('id'))->toContain($open->id)
            ->toContain($active->id)
            ->not->toContain($endedBefore->id);
    });

    it('excludes products whose availability starts after the to bound', function (): void {
        $startsAfter = Product::factory()->create(['earliest_availability_start' => now()->addDays(2)]);
        $open        = Product::factory()->create();
        $active      = Product::factory()->create(['earliest_availability_start' => now()->subDays(2)]);

        $query = ProductAvailabilityFilter::applyAvailabilityWindow(Product::query(), null, Carbon::now());

        expect($query->pluck('id'))->toContain($open->id)
            ->toContain($active->id)
            ->not->toContain($startsAfter->id);
    });

    it('keeps only products at or above the capacity utilization threshold', function (): void {
        $near = Product::factory()->create(['max_capacity_utilization' => 0.9]);
        $far  = Product::factory()->create(['max_capacity_utilization' => 0.5]);

        $query = ProductAvailabilityFilter::applyNearCapacity(Product::query(), 0.8);

        expect($query->pluck('id'))->toContain($near->id)
            ->not->toContain($far->id);
    });

    it('clamps thresholds above one so utilization above the full range still qualifies', function (): void {
        $full = Product::factory()->create(['max_capacity_utilization' => 1.2]);

        $query = ProductAvailabilityFilter::applyNearCapacity(Product::query(), 1.5);

        expect($query->pluck('id'))->toContain($full->id);
    });

    it('clamps negative thresholds to zero so any utilization qualifies', function (): void {
        $barelyUsed = Product::factory()->create(['max_capacity_utilization' => 0.4]);

        $query = ProductAvailabilityFilter::applyNearCapacity(Product::query(), -1);

        expect($query->pluck('id'))->toContain($barelyUsed->id);
    });

    it('delegates event status to the availabilityStatus scope', function (): void {
        $sql = ProductAvailabilityFilter::applyEventStatus(Product::query(), AvailabilityStatusEnum::PAST)->toSql();

        expect($sql)->toBe(Product::query()->availabilityStatus(AvailabilityStatusEnum::PAST)->toSql());
    });

    it('classifies products as past when the event ended before today', function (): void {
        $past   = Product::factory()->create(['event_ended_at' => now()->subDays(2)]);
        $future = Product::factory()->create(['event_ended_at' => now()->addDays(2)]);

        $query = ProductAvailabilityFilter::applyEventStatus(Product::query(), AvailabilityStatusEnum::PAST);

        expect($query->pluck('id'))->toContain($past->id)
            ->not->toContain($future->id);
    });

    it('delegates event-not-ended to the eventNotEnded scope', function (): void {
        $sql = ProductAvailabilityFilter::applyEventNotEnded(Product::query())->toSql();

        expect($sql)->toBe(Product::query()->eventNotEnded()->toSql());
    });

    it('excludes products whose event has already ended', function (): void {
        $ended     = Product::factory()->create(['event_ended_at' => now()->subDays(2)]);
        $openEnded = Product::factory()->create();
        $ongoing   = Product::factory()->create(['event_ended_at' => now()->addDays(2)]);

        $query = ProductAvailabilityFilter::applyEventNotEnded(Product::query());

        expect($query->pluck('id'))->toContain($openEnded->id)
            ->toContain($ongoing->id)
            ->not->toContain($ended->id);
    });
});

describe('delivery-option availability path', function (): void {
    beforeEach(function (): void {
        config()->set('products.availability.use_denormalized', false);
    });

    it('includes products with a delivery option open in all windows', function (): void {
        $open = Product::factory()->create();
        ProductDeliveryOption::factory()->for($open)->create();

        $closed = Product::factory()->create();
        ProductDeliveryOption::factory()->for($closed)->create(['registration_start_date' => now()->addDays(2)]);

        $unavailable = Product::factory()->create();
        ProductDeliveryOption::factory()->for($unavailable)->create(['available_from' => now()->addDays(2)]);

        $noOption = Product::factory()->create();

        $query = ProductAvailabilityFilter::applyAvailableNow(Product::query());

        expect($query->pluck('id'))->toContain($open->id)
            ->not->toContain($closed->id)
            ->not->toContain($unavailable->id)
            ->not->toContain($noOption->id);
    });

    it('includes products with an active term and excludes inactive terms', function (): void {
        $activeTerm = Product::factory()->create();

        $inactiveTerm        = Term::factory()->create(['status' => TermStatusEnum::INACTIVE->value]);
        $inactiveTermProduct = Product::factory()->create(['term_id' => $inactiveTerm->id]);

        $query = ProductAvailabilityFilter::applyActiveTerm(Product::query());

        expect($query->pluck('id'))->toContain($activeTerm->id)
            ->not->toContain($inactiveTermProduct->id);
    });

    it('keeps products with a delivery option at or above the capacity utilization threshold', function (): void {
        $near = Product::factory()->create();
        ProductDeliveryOption::factory()->for($near)->create([
            'capacity'       => 10,
            'enrolled_count' => 8,
            'reserved_count' => 0,
        ]);

        $far = Product::factory()->create();
        ProductDeliveryOption::factory()->for($far)->create([
            'capacity'       => 10,
            'enrolled_count' => 4,
            'reserved_count' => 0,
        ]);

        $noCapacity = Product::factory()->create();
        ProductDeliveryOption::factory()->for($noCapacity)->create(['capacity' => null]);

        $query = ProductAvailabilityFilter::applyNearCapacity(Product::query(), 0.8);

        expect($query->pluck('id'))->toContain($near->id)
            ->not->toContain($far->id)
            ->not->toContain($noCapacity->id);
    });
});
