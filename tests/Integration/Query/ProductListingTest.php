<?php

declare(strict_types=1);

namespace Tests\Integration\Query;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\ProductPrice;
use App\Query\ProductListing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

it('ignores sort fields that are not in the allowed enum', function (): void {
    $query = ProductListing::sortBy(Product::query(), 'bogus_field', 'asc');

    expect($query)->toBeInstanceOf(Builder::class)
        ->and($query->toSql())->toBe(Product::query()->toSql())
        ->and($query->toSql())->not->toContain('order by');
});

it('ignores sort directions other than asc and desc', function (): void {
    $query = ProductListing::sortBy(Product::query(), 'name', 'sideways');

    expect($query->toSql())->not->toContain('order by');
});

it('orders by the products created_at column when sorting by created_at', function (): void {
    $sql = ProductListing::sortBy(Product::query(), 'created_at', 'asc')->toSql();

    expect($sql)->toContain('order by "products"."created_at" asc');
});

it('joins product_prices and orders by min_price when sorting by price', function (): void {
    $sql = ProductListing::sortBy(Product::query(), 'price', 'asc')->toSql();

    expect($sql)->toContain('join')
        ->toContain('product_prices')
        ->toContain('min_price')
        ->toContain('order by "product_prices"."min_price" asc');
});

it('returns the cheapest product first when sorting by price ascending', function (): void {
    $expensive = Product::factory()->create(['name' => 'Expensive']);
    $cheap     = Product::factory()->create(['name' => 'Cheap']);
    $mid       = Product::factory()->create(['name' => 'Mid']);

    ProductPrice::create([
        'product_id'         => $expensive->id,
        'min_price'          => 300,
        'min_original_price' => 300,
        'max_price'          => 300,
        'max_original_price' => 300,
    ]);
    ProductPrice::create([
        'product_id'         => $cheap->id,
        'min_price'          => 100,
        'min_original_price' => 100,
        'max_price'          => 100,
        'max_original_price' => 100,
    ]);
    ProductPrice::create([
        'product_id'         => $mid->id,
        'min_price'          => 200,
        'min_original_price' => 200,
        'max_price'          => 200,
        'max_original_price' => 200,
    ]);

    $results = ProductListing::sortBy(Product::query(), 'price', 'asc')->get();

    expect($results)->toHaveCount(3)
        ->and($results->first()->is($cheap))->toBeTrue()
        ->and($results->last()->is($expensive))->toBeTrue();
});

it('delegates capacity_utilization sorting to the denormalized scope', function (): void {
    config()->set('products.availability.use_denormalized', true);

    $sql = ProductListing::sortBy(Product::query(), 'capacity_utilization')->toSql();

    expect($sql)->toContain('CASE WHEN')
        ->toContain('max_capacity_utilization')
        ->toContain('order by');
});

it('orders by the delivery option capacity ratio when the denormalized flag is off', function (): void {
    config()->set('products.availability.use_denormalized', false);

    $sql = ProductListing::sortBy(Product::query(), 'capacity_utilization')->toSql();

    expect($sql)->toContain('capacity')
        ->toContain('order by');
});

it('orders by the order items count when sorting by popularity', function (): void {
    $sql = ProductListing::popular(Product::query())->toSql();

    expect($sql)->toContain('order_items_count');
});

it('returns products with more order items first', function (): void {
    $popular       = Product::factory()->create(['name' => 'Popular']);
    $lessPopular   = Product::factory()->create(['name' => 'Less Popular']);
    $popularOption = ProductDeliveryOption::factory()->for($popular)->create();
    ProductDeliveryOption::factory()->for($lessPopular)->create();
    $order = Order::factory()->create();

    OrderItem::factory()->create([
        'order_id'                   => $order->id,
        'product_delivery_option_id' => $popularOption->id,
    ]);

    $results = ProductListing::popular(Product::query())->get();

    expect($results)->toHaveCount(2)
        ->and($results->first()->is($popular->fresh()))->toBeTrue()
        ->and($results->last()->is($lessPopular->fresh()))->toBeTrue();
});

it('forListing returns the same builder and applies the listing preset', function (): void {
    $query  = Product::query();
    $result = ProductListing::forListing($query);

    expect($result)->toBe($query)
        ->and($result->toSql())->toBe(Product::query()->forListing()->toSql())
        ->and(array_keys($result->getEagerLoads()))->toBe([
            'vendor',
            'categories',
            'productDeliveryOptions',
            'productable',
        ]);
});

it('forDetail returns the same builder and applies the detail preset', function (): void {
    $query  = Product::query();
    $result = ProductListing::forDetail($query);

    expect($result)->toBe($query)
        ->and($result->toSql())->toBe(Product::query()->forDetail()->toSql())
        ->and(array_keys($result->getEagerLoads()))->toBe([
            'vendor',
            'categories',
            'productDeliveryOptions',
            'productableWithAllRelations',
        ]);
});

it('paginates into a LengthAwarePaginator with correct metadata', function (): void {
    Product::factory()->count(5)->create();

    $paginator = ProductListing::paginate(Product::query(), 2);

    expect($paginator)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($paginator->total())->toBe(5)
        ->and($paginator->perPage())->toBe(2)
        ->and($paginator->count())->toBe(2)
        ->and($paginator->hasMorePages())->toBeTrue();
});
