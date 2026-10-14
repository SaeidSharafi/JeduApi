<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\Category;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\Product;
use App\Models\Seminar;
use App\Services\CataloguePriceBoundService;
use App\Services\ProductPriceService;
use Illuminate\Database\Eloquent\Model;

covers(CataloguePriceBoundService::class);

dataset('bound_listings', [
    'courses'        => [ProductableEnum::COURSE, 'api.v1.shop.courses.index'],
    'seminars'       => [ProductableEnum::SEMINAR, 'api.v1.shop.seminars.index'],
    'digital assets' => [ProductableEnum::DIGITAL_ASSET, 'api.v1.shop.digital-assets.index'],
]);

dataset('bound_category_listings', [
    'category courses'        => [ProductableEnum::COURSE, 'api.v1.shop.categories.courses'],
    'category seminars'       => [ProductableEnum::SEMINAR, 'api.v1.shop.categories.seminars'],
    'category digital assets' => [ProductableEnum::DIGITAL_ASSET, 'api.v1.shop.categories.digital-assets'],
]);

/**
 * Build a productable of the given type in the requested publication state.
 */
function catalogueBoundProductable(
    ProductableEnum $type,
    PublicationStatusEnum $status = PublicationStatusEnum::PUBLISHED,
): Course|Seminar|DigitalAsset {
    return match ($type) {
        ProductableEnum::COURSE        => Course::factory()->create(['status' => $status]),
        ProductableEnum::SEMINAR       => Seminar::factory()->create(['status' => $status]),
        ProductableEnum::DIGITAL_ASSET => DigitalAsset::factory()->create(['status' => $status]),
        default                        => throw new InvalidArgumentException("Unsupported productable type [{$type->value}]."),
    };
}

/**
 * Create an available, published product of $type whose only delivery option
 * costs $price, and materialise its Purchase Price row.
 *
 * @param  array<string, mixed>  $productAttributes
 */
function createCatalogueBoundProduct(
    ProductableEnum $type,
    int $price,
    string $name,
    array $productAttributes = [],
    string $fulfillmentType = FulfillmentTypeEnum::DIGITAL->value,
    ?Model $productable = null,
): Product {
    $factory = Product::factory()->withDeliveryOptions(1, [[
        'price'            => $price,
        'fulfillment_type' => $fulfillmentType,
    ]]);

    $factory = match ($type) {
        ProductableEnum::COURSE        => $factory->withCourse($productable ?? catalogueBoundProductable($type)),
        ProductableEnum::SEMINAR       => $factory->withSeminar($productable ?? catalogueBoundProductable($type)),
        ProductableEnum::DIGITAL_ASSET => $factory->withDigitalAsset($productable ?? catalogueBoundProductable($type)),
        default                        => throw new InvalidArgumentException("Unsupported productable type [{$type->value}]."),
    };

    $product = $factory->create(['name' => $name, ...$productAttributes]);

    app(ProductPriceService::class)->updatePriceIndex($product->fresh());

    return $product;
}

it('returns the bound of its own catalogue on every type listing', function (ProductableEnum $type, string $routeName): void {
    createCatalogueBoundProduct($type, 100_000, 'Cheap Product');
    createCatalogueBoundProduct($type, 300_000, 'Premium Product');

    $this->getJson(route($routeName))
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
})->with('bound_listings');

it('reports the same catalogue bound whatever product filter the request carries', function (array $query, int $expectedCount): void {
    $category = Category::factory()->create(['slug' => 'budget-courses']);

    createCatalogueBoundProduct(ProductableEnum::COURSE, 100_000, 'Cheap Course')->categories()->attach($category->id);
    createCatalogueBoundProduct(ProductableEnum::COURSE, 300_000, 'Premium Course', fulfillmentType: FulfillmentTypeEnum::ONLINE_SERVICE->value);

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

it('ignores products outside the default listing scope when it computes the bound', function (ProductableEnum $type, string $routeName): void {
    createCatalogueBoundProduct($type, 150_000, 'Listed Product');

    createCatalogueBoundProduct($type, 10_000, 'Hidden Product', ['is_visible' => false]);
    createCatalogueBoundProduct($type, 20_000, 'Draft Product', ['status' => PublicationStatusEnum::DRAFT->value]);
    createCatalogueBoundProduct(
        $type,
        30_000,
        'Unpublished Productable',
        productable: catalogueBoundProductable($type, PublicationStatusEnum::DRAFT),
    );

    $this->getJson(route($routeName))
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('metadata.filters.price', ['min' => 150_000, 'max' => 150_000]);
})->with('bound_listings');

it('reports null bound members when the catalogue is empty', function (ProductableEnum $type, string $routeName): void {
    $this->getJson(route($routeName))
        ->assertOk()
        ->assertJsonCount(0, 'data.data')
        ->assertJsonPath('metadata.filters.price', ['min' => null, 'max' => null]);
})->with('bound_listings');

it('serves the cached empty bound instead of regenerating it', function (): void {
    $this->getJson(route('api.v1.shop.courses.index'))
        ->assertOk()
        ->assertJsonPath('metadata.filters.price', ['min' => null, 'max' => null]);

    // Written straight through the factory and the price index, bypassing every
    // catalog write path, so nothing clears the bound the first request cached.
    createCatalogueBoundProduct(ProductableEnum::COURSE, 500_000, 'Written Behind The Gateway');

    $this->getJson(route('api.v1.shop.courses.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('metadata.filters.price', ['min' => null, 'max' => null]);
});

it('returns the bound of the requested category for the endpoint productable type', function (ProductableEnum $type, string $routeName): void {
    $budget = Category::factory()->create(['slug' => 'budget']);
    $luxury = Category::factory()->create(['slug' => 'luxury']);

    createCatalogueBoundProduct($type, 100_000, 'Budget Cheap')->categories()->attach($budget->id);
    createCatalogueBoundProduct($type, 300_000, 'Budget Premium')->categories()->attach($budget->id);
    createCatalogueBoundProduct($type, 5_000_000, 'Luxury Premium')->categories()->attach($luxury->id);
    createCatalogueBoundProduct($type, 1, 'Outside Every Category');

    $this->getJson(route($routeName, $budget))
        ->assertOk()
        ->assertJsonCount(2, 'data.data')
        ->assertJsonPath('metadata.filters.price', ['min' => 100_000, 'max' => 300_000]);

    $this->getJson(route($routeName, $luxury))
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('metadata.filters.price', ['min' => 5_000_000, 'max' => 5_000_000]);
})->with('bound_category_listings');

it('ignores out-of-scope and other-category products in the category bound', function (ProductableEnum $type, string $routeName): void {
    $category = Category::factory()->create(['slug' => 'listed']);
    $other    = Category::factory()->create(['slug' => 'elsewhere']);

    createCatalogueBoundProduct($type, 150_000, 'Listed Product')->categories()->attach($category->id);
    createCatalogueBoundProduct($type, 10_000, 'Hidden Product', ['is_visible' => false])->categories()->attach($category->id);
    createCatalogueBoundProduct($type, 20_000, 'Draft Product', ['status' => PublicationStatusEnum::DRAFT->value])->categories()->attach($category->id);
    createCatalogueBoundProduct(
        $type,
        30_000,
        'Unpublished Productable',
        productable: catalogueBoundProductable($type, PublicationStatusEnum::DRAFT),
    )->categories()->attach($category->id);
    createCatalogueBoundProduct($type, 40_000, 'Other Category Product')->categories()->attach($other->id);

    $this->getJson(route($routeName, $category))
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('metadata.filters.price', ['min' => 150_000, 'max' => 150_000]);
})->with('bound_category_listings');

it('reports null bounds for a category with no available products', function (ProductableEnum $type, string $routeName): void {
    $category  = Category::factory()->create(['slug' => 'empty']);
    $otherType = $type === ProductableEnum::COURSE ? ProductableEnum::SEMINAR : ProductableEnum::COURSE;

    createCatalogueBoundProduct($otherType, 200_000, 'Other Type Product')->categories()->attach($category->id);

    $this->getJson(route($routeName, $category))
        ->assertOk()
        ->assertJsonCount(0, 'data.data')
        ->assertJsonPath('metadata.filters.price', ['min' => null, 'max' => null]);
})->with('bound_category_listings');

/**
 * A category listing deliberately carries no availability window (its bound is the
 * scope `CategoryQueryService` lists with), so it bounds the products the type
 * listing's default available-now window excludes.
 */
it('bounds a category by its catalogue even when that catalogue is outside the availability window', function (ProductableEnum $type, string $categoryRoute, string $listingRoute): void {
    $category = Category::factory()->create(['slug' => 'archive']);

    $archived = createCatalogueBoundProduct($type, 150_000, 'Archived Product');
    $archived->categories()->attach($category->id);

    // Its only delivery option ended last month, so the type listing's default
    // availability window leaves the type-wide catalogue empty.
    $archived->productDeliveryOptions()->update([
        'available_from' => now()->subMonths(2),
        'available_to'   => now()->subMonth(),
    ]);

    $this->getJson(route($listingRoute))
        ->assertOk()
        ->assertJsonCount(0, 'data.data')
        ->assertJsonPath('metadata.filters.price', ['min' => null, 'max' => null]);

    $this->getJson(route($categoryRoute, $category))
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('metadata.filters.price', ['min' => 150_000, 'max' => 150_000]);
})->with([
    'category courses'        => [ProductableEnum::COURSE, 'api.v1.shop.categories.courses', 'api.v1.shop.courses.index'],
    'category seminars'       => [ProductableEnum::SEMINAR, 'api.v1.shop.categories.seminars', 'api.v1.shop.seminars.index'],
    'category digital assets' => [ProductableEnum::DIGITAL_ASSET, 'api.v1.shop.categories.digital-assets', 'api.v1.shop.digital-assets.index'],
]);

it('keeps the price bound off the listings that do not own it', function (Closure $route): void {
    $this->getJson($route())
        ->assertOk()
        ->assertJsonPath('metadata', []);
})->with([
    'bundles'        => [fn (): string => route('api.v1.shop.bundles.index')],
    'good for start' => [function (): string {
        return route('api.v1.shop.categories.courses.good-for-start', Category::factory()->create());
    }],
    'category detail' => [function (): string {
        return route('api.v1.shop.categories.show', Category::factory()->create());
    }],
]);
