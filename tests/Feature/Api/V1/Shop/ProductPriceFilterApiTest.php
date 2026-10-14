<?php

declare(strict_types=1);

use App\Data\Shop\Product\Course\ProductFilterData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\Product;
use App\Models\Seminar;
use App\Services\ProductPriceService;

covers(ProductFilterData::class);

/**
 * Create a published product of the requested type whose only delivery option costs $price.
 */
function createPriceFilterProduct(string $factoryMethod, string $name, int $price): Product
{
    $productable = match ($factoryMethod) {
        'withCourse'       => Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
        'withSeminar'      => Seminar::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
        'withDigitalAsset' => DigitalAsset::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]),
    };

    $product = Product::factory()
        ->withDeliveryOptions(1, [[
            'price'            => $price,
            'fulfillment_type' => FulfillmentTypeEnum::DIGITAL->value,
        ]])
        ->$factoryMethod($productable)
        ->create(['name' => $name]);

    app(ProductPriceService::class)->updatePriceIndex($product->fresh());

    return $product;
}

describe('Product price range filter - :dataset', function (): void {
    it('accepts a maximum price on its own and filters on that ceiling', function (string $factoryMethod, string $routePrefix): void {
        createPriceFilterProduct($factoryMethod, 'Budget Product', 100_000);
        createPriceFilterProduct($factoryMethod, 'Premium Product', 300_000);

        $response = $this->getJson(route("api.v1.shop.{$routePrefix}.index", [
            'filter' => ['max_price' => '100000'],
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['name' => 'Budget Product'])
            ->assertJsonMissing(['name' => 'Premium Product']);
    })->with('product_types');

    it('accepts equal minimum and maximum bounds and returns products at exactly that price', function (string $factoryMethod, string $routePrefix): void {
        createPriceFilterProduct($factoryMethod, 'Budget Product', 100_000);
        createPriceFilterProduct($factoryMethod, 'Premium Product', 300_000);

        $response = $this->getJson(route("api.v1.shop.{$routePrefix}.index", [
            'filter' => ['min_price' => '100000', 'max_price' => '100000'],
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['name' => 'Budget Product'])
            ->assertJsonMissing(['name' => 'Premium Product']);
    })->with('product_types');

    it('rejects a maximum price below the minimum price', function (string $factoryMethod, string $routePrefix): void {
        $response = $this->getJson(route("api.v1.shop.{$routePrefix}.index", [
            'filter' => ['min_price' => '100000', 'max_price' => '50000'],
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['filter.max_price']);
    })->with('product_types');
})->group('product-listing');

describe('Search price range filter', function (): void {
    beforeEach(function (): void {
        Config::set('scout.driver', 'database');
    });

    it('accepts a maximum price on its own and filters on that ceiling', function (): void {
        createPriceFilterProduct('withCourse', 'Budget Product', 100_000);
        createPriceFilterProduct('withCourse', 'Premium Product', 300_000);

        $response = $this->getJson(route('api.v1.shop.search', [
            'q'      => 'Product',
            'filter' => ['max_price' => '100000'],
        ]));

        $response->assertOk();

        $names = collect($response->json('data.data'))->pluck('name');

        expect($names)->toContain('Budget Product')
            ->and($names)->not->toContain('Premium Product');
    });
});
