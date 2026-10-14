<?php

declare(strict_types=1);

use App\Data\Shop\Product\Course\ProductFilterData;
use App\Enums\Product\AvailabilityStatusEnum;
use App\Enums\Product\ProductableEnum;
use App\Query\ProductListingScope;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

covers(ProductListingScope::class);

/**
 * The reference instant is fixed so the rendered Typesense expression is a plain string:
 * 2025-03-15 12:00:00 UTC and the start of that day in UTC.
 */
$now = fn (): CarbonImmutable => CarbonImmutable::parse('2025-03-15 12:00:00', 'UTC');

it('renders the publish invariants, productable type and the default availability window', function () use ($now): void {
    $scope = ProductListingScope::forListing(ProductableEnum::COURSE, null, $now());

    expect($scope->toTypesenseFilter())->toBe(
        'status:=published && is_visible:=true && productable_type:!=bundle'
        .' && productable_status:=published && has_published_delivery_option:=true && is_term_active:=true'
        .' && productable_type:=course'
        .' && earliest_availability_start_ts:<=1742040000 && latest_availability_end_ts:>=1742040000'
        .' && latest_event_ended_ts:>=1741996800'
    );
});

it('renders every user-facing filter as one expression', function () use ($now): void {
    $filter = new ProductFilterData(
        category_slugs: ['programming', 'design'],
        fulfillment_types: ['online_service', 'offline_service'],
        difficulty_level: 'beginner',
        min_price: 100_000,
        max_price: 500_000,
        with_discounts: true,
        near_capacity_only: true,
        capacity_threshold: 0.9,
    );

    $scope = ProductListingScope::forListing(ProductableEnum::COURSE, $filter, $now());

    expect($scope->toTypesenseFilter())->toBe(
        'status:=published && is_visible:=true && productable_type:!=bundle'
        .' && productable_status:=published && has_published_delivery_option:=true && is_term_active:=true'
        .' && productable_type:=course'
        .' && has_discount:=true && category_slugs:=[programming,design] && price:[100000..500000]'
        .' && difficulty_level:=beginner'
        .' && (fulfillment_types:=online_service || fulfillment_types:=offline_service)'
        .' && max_capacity_utilization:>=0.9'
        .' && earliest_availability_start_ts:<=1742040000 && latest_availability_end_ts:>=1742040000'
        .' && latest_event_ended_ts:>=1741996800'
    );
});

it('renders a single price bound without the other', function (?int $minPrice, ?int $maxPrice, string $expectedBound, string $absentBound) use ($now): void {
    $filter = new ProductFilterData(min_price: $minPrice, max_price: $maxPrice);

    $expression = ProductListingScope::forListing(ProductableEnum::COURSE, $filter, $now())->toTypesenseFilter();

    expect($expression)->toContain($expectedBound)
        ->and($expression)->not->toContain($absentBound);
})->with([
    'minimum only' => [100_000, null, 'price:>=100000', 'price:<='],
    'maximum only' => [null, 500_000, 'price:<=500000', 'price:>='],
]);

it('renders the available-now window and lets it supersede the default window', function () use ($now): void {
    $filter = new ProductFilterData(is_available_now: true, availability_status: AvailabilityStatusEnum::PAST->value);

    $expression = ProductListingScope::forListing(ProductableEnum::COURSE, $filter, $now())->toTypesenseFilter();

    expect($expression)->toContain('earliest_registration_start_ts:<=1742040000')
        ->toContain('latest_registration_end_ts:>=1742040000')
        ->toContain('earliest_availability_start_ts:<=1742040000')
        ->toContain('latest_availability_end_ts:>=1742040000')
        ->not->toContain('latest_event_ended_ts');
});

it('renders the registration window on top of the default availability window', function () use ($now): void {
    $filter = new ProductFilterData(
        registration_starts_after: Carbon::parse('2025-09-01', 'UTC'),
        registration_ends_before: Carbon::parse('2025-06-20', 'UTC'),
    );

    $expression = ProductListingScope::forListing(ProductableEnum::COURSE, $filter, $now())->toTypesenseFilter();

    expect($expression)->toContain('latest_registration_end_ts:>=1756684800')
        ->toContain('earliest_registration_start_ts:<=1750377600')
        ->toContain('earliest_availability_start_ts:<=1742040000')
        ->toContain('latest_availability_end_ts:>=1742040000')
        ->toContain('latest_event_ended_ts:>=1741996800');
});

it('renders an explicit availability window instead of the default one', function () use ($now): void {
    $filter = new ProductFilterData(
        available_from: Carbon::parse('2025-06-20', 'UTC'),
        available_to: Carbon::parse('2025-09-01', 'UTC'),
    );

    $expression = ProductListingScope::forListing(ProductableEnum::COURSE, $filter, $now())->toTypesenseFilter();

    expect($expression)->toContain('latest_availability_end_ts:>=1750377600')
        ->toContain('earliest_availability_start_ts:<=1756684800')
        ->not->toContain('latest_event_ended_ts');
});

it('renders each availability status', function (AvailabilityStatusEnum $status, string $expected) use ($now): void {
    $filter = new ProductFilterData(availability_status: $status->value);

    $expression = ProductListingScope::forListing(ProductableEnum::COURSE, $filter, $now())->toTypesenseFilter();

    expect($expression)->toContain($expected)
        ->not->toContain('earliest_availability_start_ts:<=1742040000');
})->with([
    'past'     => [AvailabilityStatusEnum::PAST, 'latest_event_ended_ts:<1741996800'],
    'upcoming' => [AvailabilityStatusEnum::UPCOMING, 'earliest_event_start_ts:>1741996800'],
    'ongoing'  => [AvailabilityStatusEnum::ONGOING, 'earliest_event_start_ts:<=1741996800 && latest_event_ended_ts:>=1741996800'],
]);

it('renders the global search product scope without catalogue or availability clauses', function () use ($now): void {
    $filter = new ProductFilterData(
        with_discounts: false,
        min_price: 100_000,
        near_capacity_only: true,
        capacity_threshold: 0.5,
    );

    $scope = ProductListingScope::forGlobalSearch(ProductableEnum::COURSE, $filter, $now());

    expect($scope->toTypesenseFilter())->toBe(
        'status:=published && is_visible:=true && productable_type:!=bundle'
        .' && productable_type:=course && has_discount:=false && price:>=100000'
    );
});

it('does not render an explicit negative discount for the listing scope', function () use ($now): void {
    $filter = new ProductFilterData(with_discounts: false);

    $expression = ProductListingScope::forListing(ProductableEnum::COURSE, $filter, $now())->toTypesenseFilter();

    expect($expression)->not->toContain('has_discount');
});

it('falls back to the configured capacity threshold when the filter omits one', function () use ($now): void {
    $filter = new ProductFilterData(near_capacity_only: true);

    $expression = ProductListingScope::forListing(ProductableEnum::COURSE, $filter, $now())->toTypesenseFilter();

    expect($expression)->toContain('max_capacity_utilization:>=0.8');
});

it('does not render the capacity clause when the filter disables it', function () use ($now): void {
    $filter = new ProductFilterData(near_capacity_only: false);

    $expression = ProductListingScope::forListing(ProductableEnum::COURSE, $filter, $now())->toTypesenseFilter();

    expect($expression)->not->toContain('max_capacity_utilization');
});

it('stacks the registration window with an availability status', function () use ($now): void {
    $filter = new ProductFilterData(
        availability_status: AvailabilityStatusEnum::PAST->value,
        registration_starts_after: Carbon::parse('2025-09-01', 'UTC'),
    );

    $expression = ProductListingScope::forListing(ProductableEnum::COURSE, $filter, $now())->toTypesenseFilter();

    expect($expression)->toContain('latest_registration_end_ts:>=1756684800')
        ->toContain('latest_event_ended_ts:<1741996800')
        ->not->toContain('latest_availability_end_ts:>=1742040000');
});

it('renders a one-sided availability window instead of the default one', function (?Carbon $from, ?Carbon $to, string $expected) use ($now): void {
    $filter = new ProductFilterData(available_from: $from, available_to: $to);

    $expression = ProductListingScope::forListing(ProductableEnum::COURSE, $filter, $now())->toTypesenseFilter();

    expect($expression)->toContain($expected)
        ->not->toContain('latest_event_ended_ts');
})->with([
    'from only' => [Carbon::parse('2025-06-20', 'UTC'), null, 'latest_availability_end_ts:>=1750377600'],
    'to only'   => [null, Carbon::parse('2025-09-01', 'UTC'), 'earliest_availability_start_ts:<=1756684800'],
]);
