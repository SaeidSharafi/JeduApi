<?php

use App\Enums\DeliveryMethodEnum;
use App\Enums\FulfillmentTypeEnum;
use App\Jobs\UpdateProductPriceCacheJob;
use App\Models\Product;

describe('IndexAllProductPricesCommand', function () {


    it('dispatches jobs for all products', function () {
        Bus::fake();

        $products = Product::factory()->count(5)->create();

        Artisan::call('prices:index-all');

        foreach ($products as $product) {
            Bus::assertDispatched(UpdateProductPriceCacheJob::class, function ($job) use ($product) {
                return $job->productId === $product->id;
            });
        }

        Bus::assertDispatchedTimes(UpdateProductPriceCacheJob::class, $products->count());
    });

    it('handles no products gracefully', function () {
        Bus::fake();

        Product::query()->delete();

        Artisan::call('prices:index-all');

        Bus::assertNotDispatched(UpdateProductPriceCacheJob::class);
    });

    it('runs jobs synchronously when --sync option is provided', function () {
        $product = Product::factory()->withDeliveryOptions(
            realData: [
                [
                    'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE,
                    'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE,
                    'price'            => 1000000,
                ],
            ]
        )
            ->create();

        $this->assertNull($product->price_data_cache);

        $this->artisan('prices:index-all', ['--sync' => true]);

        $product->refresh();
        $this->assertNotNull($product->price_data_cache);
        $this->assertJson($product->price_data_cache);
    });
});
