<?php

declare(strict_types=1);

use App\Events\ProductSearchIndexInvalidated;
use App\Jobs\UpdateProductPricingJob;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\ProductPrice;
use App\Services\ProductPriceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

it('handle empty product ids', function (): void {
    $job = new UpdateProductPricingJob([]);
    $job->handle(app(ProductPriceService::class));
    $this->assertTrue(true);
});

it('handle non existing product ids', function (): void {
    $job = new UpdateProductPricingJob([9999, 10000]);
    $job->handle(app(ProductPriceService::class));
    $this->assertTrue(true);
});

it('synchronizes search only when indexed pricing values change', function (): void {
    $product = Product::withoutSyncingToSearch(fn(): Product => Product::factory()->create());
    ProductDeliveryOption::factory()->create([
        'product_id' => $product->id,
        'price'      => 250_000,
    ]);

    $transactionManager = fakeAfterCommitEventsImmediately(ProductSearchIndexInvalidated::class);
    (new UpdateProductPricingJob([$product->id]))->handle(app(ProductPriceService::class));
    restoreAfterCommitEventManager($transactionManager);

    Event::assertDispatched(ProductSearchIndexInvalidated::class,
        fn(ProductSearchIndexInvalidated $event): bool => $event->productIds === [$product->id]);

    $transactionManager = fakeAfterCommitEventsImmediately(ProductSearchIndexInvalidated::class);
    (new UpdateProductPricingJob([$product->id]))->handle(app(ProductPriceService::class));
    restoreAfterCommitEventManager($transactionManager);

    Event::assertNotDispatched(ProductSearchIndexInvalidated::class);
});

it('persists the correct price cache for every product in the batch', function (): void {
    Event::fake([ProductSearchIndexInvalidated::class]);

    $product = Product::withoutSyncingToSearch(fn (): Product => Product::factory()->create());

    $cheapOption = ProductDeliveryOption::factory()->create([
        'product_id'              => $product->id,
        'price'                   => 100_000,
        'is_featured'             => false,
        'featured_price'          => 0,
        'is_prepayment_available' => false,
        'prepayment_amount'       => 0,
    ]);

    $expensiveOption = ProductDeliveryOption::factory()->create([
        'product_id'              => $product->id,
        'price'                   => 200_000,
        'is_featured'             => false,
        'featured_price'          => 0,
        'is_prepayment_available' => false,
        'prepayment_amount'       => 0,
    ]);

    (new UpdateProductPricingJob([$product->id]))->handle(app(ProductPriceService::class));

    $expectedOptionPrice = static fn (int $price, string $uuid): array => [
        'current_price'         => $price,
        'original_price'        => $price,
        'pre_payment_price'     => null,
        'featured_price'        => null,
        'discount_amount'       => null,
        'has_pre_payment_price' => false,
        'has_featured_price'    => false,
        'has_discount'          => false,
        'discount_type'         => null,
        'discount_percentage'   => null,
        'range'                 => null,
        'uuid'                  => $uuid,
    ];

    $expected = [
        'min_price'               => 100_000,
        'min_original_price'      => 100_000,
        'has_featured_price'      => false,
        'has_discount'            => false,
        'has_pre_payment'         => false,
        'discount_type'           => null,
        'discount_percentage'     => null,
        'highest_discount_amount' => null,
        'range'                   => ['min' => 100_000, 'max' => 200_000],
        'prices'                  => [
            $cheapOption->uuid     => $expectedOptionPrice(100_000, $cheapOption->uuid),
            $expensiveOption->uuid => $expectedOptionPrice(200_000, $expensiveOption->uuid),
        ],
    ];

    $actual           = $product->fresh()->price_data_cache;
    $actual['prices'] = collect($actual['prices'])->keyBy('uuid')->all();

    expect($actual)->toEqual($expected);
});

it('keeps the query count of a batch independent of the number of products with active prices', function (): void {
    Event::fake([ProductSearchIndexInvalidated::class]);

    $productsWithTwoOptions = static fn(int $productCount) => Product::withoutSyncingToSearch(fn() => Product::factory()
        ->count($productCount)
        ->has(ProductDeliveryOption::factory()->count(2))
        ->create());

    $countBatchQueries = static fn(Collection $products): int => captureExecutedQueries(fn(
    ) => (new UpdateProductPricingJob($products->modelKeys()))
        ->handle(app(ProductPriceService::class)))
        ->count();

    expect($countBatchQueries($productsWithTwoOptions(2)))
        ->toBe($countBatchQueries($productsWithTwoOptions(6)));
});

it('keeps the query count of a batch independent of the number of products with stale price rows', function (): void {
    Event::fake([ProductSearchIndexInvalidated::class]);

    $staleProducts = static function (int $productCount): Collection {
        $products = Product::withoutSyncingToSearch(fn() => Product::factory()->count($productCount)->create());

        $products->each(fn(Product $product) => $product->productPrice()->create([
            'product_id'          => $product->id,
            'min_price'           => 1000, 'min_original_price' => 1000, 'max_price' => 1000,
            'max_original_price'  => 1000,
            'has_discount'        => false, 'has_featured_price' => false, 'has_prepayment' => false,
            'discount_percentage' => 0, 'highest_discount_amount' => 0,
        ]));

        return $products;
    };

    $countBatchQueries = static fn(Collection $products): int => captureExecutedQueries(fn(
    ) => (new UpdateProductPricingJob($products->modelKeys()))
        ->handle(app(ProductPriceService::class)))
        ->count();

    expect($countBatchQueries($staleProducts(2)))
        ->toBe($countBatchQueries($staleProducts(6)));
});

it('removes stale price index rows for products with no priced delivery options', function (): void {
    Event::fake([ProductSearchIndexInvalidated::class]);

    $products = Product::withoutSyncingToSearch(fn() => Product::factory()->count(3)->create());

    $products->each(fn(Product $product) => $product->productPrice()->create([
        'product_id'          => $product->id,
        'min_price'           => 1000, 'min_original_price' => 1000, 'max_price' => 1000,
        'max_original_price'  => 1000,
        'has_discount'        => false, 'has_featured_price' => false, 'has_prepayment' => false,
        'discount_percentage' => 0, 'highest_discount_amount' => 0,
    ]));

    (new UpdateProductPricingJob($products->modelKeys()))->handle(app(ProductPriceService::class));

    expect(ProductPrice::whereIn('product_id', $products->modelKeys())->count())->toBe(0);
});

/**
 * @param  callable(): void  $callback
 *
 * @return Collection<int, string>
 */
function captureExecutedQueries(callable $callback): Collection
{
    $connection = DB::connection();

    $connection->flushQueryLog();
    $connection->enableQueryLog();

    try {
        $callback();

        return collect($connection->getQueryLog())->pluck('query');
    } finally {
        $connection->disableQueryLog();
    }
}
