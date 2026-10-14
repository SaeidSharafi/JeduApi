<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Models\Category;
use App\Models\Course;
use App\Models\Product;
use App\Services\CataloguePriceBoundService;
use App\Services\ProductPriceService;

covers(CataloguePriceBoundService::class);

/**
 * Create an available, published course product whose only delivery option costs
 * $price, and materialise its Purchase Price row.
 *
 * @param  array<string, mixed>  $productAttributes
 */
function createCatalogueBoundCourse(
    int $price,
    string $name,
    array $productAttributes = [],
    string $fulfillmentType = FulfillmentTypeEnum::DIGITAL->value,
    ?Course $course = null,
): Product {
    $course ??= Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);

    $product = Product::factory()
        ->withDeliveryOptions(1, [[
            'price'            => $price,
            'fulfillment_type' => $fulfillmentType,
        ]])
        ->withCourse($course)
        ->create(['name' => $name, ...$productAttributes]);

    app(ProductPriceService::class)->updatePriceIndex($product->fresh());

    return $product;
}

it('carries the catalogue price bound on the course listing', function (): void {
    createCatalogueBoundCourse(100_000, 'Cheap Course');
    createCatalogueBoundCourse(300_000, 'Premium Course');

    $this->getJson(route('api.v1.shop.courses.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data.data')
        ->assertJsonPath('metadata.filters.price', ['min' => 100_000, 'max' => 300_000])
        ->assertJsonStructure([
            'message',
            'data' => [
                'data' => [
                    '*' => ['slug', 'name', 'price', 'original_price', 'price_range', 'price_data'],
                ],
            ],
            'metadata' => ['filters' => ['price' => ['min', 'max']]],
        ]);
});

it('reports the same catalogue bound whatever product filter the request carries', function (array $query, int $expectedCount): void {
    $category = Category::factory()->create(['slug' => 'budget-courses']);

    createCatalogueBoundCourse(100_000, 'Cheap Course')->categories()->attach($category->id);
    createCatalogueBoundCourse(300_000, 'Premium Course', fulfillmentType: FulfillmentTypeEnum::ONLINE_SERVICE->value);

    $this->getJson(route('api.v1.shop.courses.index', $query))
        ->assertOk()
        ->assertJsonCount($expectedCount, 'data.data')
        ->assertJsonPath('metadata.filters.price', ['min' => 100_000, 'max' => 300_000]);
})->with([
    'price filter'       => [['filter' => ['min_price' => '300000']], 1],
    'category filter'    => [['filter' => ['category_slugs' => ['budget-courses']]], 1],
    'fulfillment filter' => [['filter' => ['fulfillment_types' => [FulfillmentTypeEnum::ONLINE_SERVICE->value]]], 1],
    'discount filter'    => [['filter' => ['with_discounts' => '1']], 0],
])->group('price-bound');

it('ignores products outside the default listing scope when it computes the bound', function (): void {
    createCatalogueBoundCourse(150_000, 'Listed Course');

    createCatalogueBoundCourse(10_000, 'Hidden Course', ['is_visible' => false]);
    createCatalogueBoundCourse(20_000, 'Draft Product', ['status' => PublicationStatusEnum::DRAFT->value]);
    createCatalogueBoundCourse(
        30_000,
        'Unpublished Course Product',
        course: Course::factory()->create(['status' => PublicationStatusEnum::DRAFT]),
    );

    $this->getJson(route('api.v1.shop.courses.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('metadata.filters.price', ['min' => 150_000, 'max' => 150_000]);
});

it('reports null bound members when the course catalogue is empty', function (): void {
    $this->getJson(route('api.v1.shop.courses.index'))
        ->assertOk()
        ->assertJsonCount(0, 'data.data')
        ->assertJsonPath('metadata.filters.price', ['min' => null, 'max' => null]);
});

it('serves the cached empty bound instead of regenerating it', function (): void {
    $this->getJson(route('api.v1.shop.courses.index'))
        ->assertOk()
        ->assertJsonPath('metadata.filters.price', ['min' => null, 'max' => null]);

    // Written straight through the factory and the price index, bypassing every
    // catalog write path, so nothing clears the bound the first request cached.
    createCatalogueBoundCourse(500_000, 'Written Behind The Gateway');

    $this->getJson(route('api.v1.shop.courses.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('metadata.filters.price', ['min' => null, 'max' => null]);
});

it('keeps the price bound off the listings that do not own it', function (string $routeName): void {
    $this->getJson(route($routeName))
        ->assertOk()
        ->assertJsonPath('metadata', []);
})->with([
    'seminars'       => ['api.v1.shop.seminars.index'],
    'digital assets' => ['api.v1.shop.digital-assets.index'],
    'bundles'        => ['api.v1.shop.bundles.index'],
]);
