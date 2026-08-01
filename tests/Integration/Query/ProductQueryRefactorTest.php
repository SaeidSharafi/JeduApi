<?php

declare(strict_types=1);

use App\Data\Shop\Product\Course\ProductListRequestData;
use App\Enums\Product\AvailabilityStatusEnum;
use App\Enums\Product\ProductSortFieldEnum;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Query\ProductAvailabilityFilter;
use App\Query\ProductListing;
use App\Query\ProductQueryService;
use App\Services\ProductSearch;
use Illuminate\Validation\Rules\In;

it('keeps product sort fields in one enum-backed source of truth', function (): void {
    expect(ProductSortFieldEnum::ALLOWED)->toBe([
        'created_at',
        'updated_at',
        'name',
        'short_name',
        'price',
        'capacity_utilization',
    ])->and(ProductQueryService::allowedSortFields)->toBe(ProductSortFieldEnum::ALLOWED);

    $sortRules = ProductListRequestData::rules()['sortBy'];
    $allowed   = collect($sortRules)
        ->first(fn (mixed $rule): bool => $rule instanceof In);

    expect($allowed)->not->toBeNull()
        ->and((array) $allowed)->toContain(ProductSortFieldEnum::ALLOWED);
});

it('exposes listing and detail eager-loading presets on the product query', function (): void {
    $listingRelations = array_keys(Product::query()->forListing()->getEagerLoads());
    $detailRelations  = array_keys(Product::query()->forDetail()->getEagerLoads());

    expect($listingRelations)->toBe([
        'vendor',
        'categories',
        'productDeliveryOptions',
        'productable',
    ])->and($detailRelations)->toBe([
        'vendor',
        'categories',
        'productDeliveryOptions',
        'productableWithAllRelations',
    ]);
});

it('uses direct product columns for denormalized availability scopes', function (): void {
    config()->set('products.availability.use_denormalized', true);

    $sql = Product::query()
        ->publishedAndVisible()
        ->hasPublishedDeliveryOption()
        ->publishedProductable()
        ->activeTerm()
        ->toRawSql();

    expect($sql)->toContain('has_published_delivery_option')
        ->toContain('productable_status')
        ->toContain('is_term_active')
        ->not->toContain('exists (');
});

it('classifies event dates directly while retaining the delivery-option fallback', function (): void {
    $sql = Product::query()
        ->availabilityStatus(AvailabilityStatusEnum::PAST)
        ->toRawSql();

    expect($sql)->toContain('event_ended_at')
        ->toContain('product_delivery_options')
        ->toContain('available_to');

    $eventNotEndedSql = Product::query()->eventNotEnded()->toRawSql();

    expect($eventNotEndedSql)->toContain('event_ended_at')
        ->not->toContain('product_delivery_options');
});

it('provides stateless availability and listing collaborators', function (): void {
    config()->set('products.availability.use_denormalized', true);

    $query = ProductAvailabilityFilter::applyPublishedAndVisible(Product::query());
    $query = ProductAvailabilityFilter::applyHasPublishedDeliveryOption($query);
    $query = ProductListing::sortBy($query, 'name', 'asc');

    expect($query->toRawSql())->toContain('has_published_delivery_option')
        ->toContain('order by "products"."name" asc');
});

it('falls through to database search when Scout fails', function (): void {
    config()->set('products.availability.use_denormalized', false);
    $product = Product::factory()->create();
    ProductDeliveryOption::factory()->for($product)->create();

    $search = new ProductSearch(
        typesenseAvailability: fn (): bool => true,
        scoutSearch: fn () => throw new RuntimeException('Scout unavailable'),
    );

    $results = $search->search(new ProductListRequestData());

    expect($results->contains(fn (Product $result): bool => $result->is($product)))->toBeTrue();
});
