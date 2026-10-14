<?php

declare(strict_types=1);

use App\Actions\Shop\Search\BuildSearchMetadataAction;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\Blog\BlogPost;
use App\Models\Bundle;
use App\Models\Category;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\Product;
use App\Models\Seminar;
use App\Services\ProductPriceService;

covers(BuildSearchMetadataAction::class);

beforeEach(function (): void {
    Config::set('scout.driver', 'database');
});

/**
 * Create an available, published product of $type whose only delivery option costs
 * $price, and materialise its Purchase Price row.
 *
 * @param  array<string, mixed>  $productAttributes
 */
function createSearchableProduct(
    int $price,
    string $name,
    ProductableEnum $type = ProductableEnum::COURSE,
    array $productAttributes = [],
): Product {
    $factory = Product::factory()->withDeliveryOptions(1, [[
        'price'            => $price,
        'fulfillment_type' => FulfillmentTypeEnum::DIGITAL->value,
    ]]);

    $factory = match ($type) {
        ProductableEnum::COURSE        => $factory->withCourse(Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED])),
        ProductableEnum::SEMINAR       => $factory->withSeminar(Seminar::factory()->create(['status' => PublicationStatusEnum::PUBLISHED])),
        ProductableEnum::DIGITAL_ASSET => $factory->withDigitalAsset(DigitalAsset::factory()->create(['status' => PublicationStatusEnum::PUBLISHED])),
        ProductableEnum::BUNDLE        => $factory->state([
            'productable_type' => ProductableEnum::BUNDLE->value,
            'productable_id'   => Bundle::factory()->create(['status' => PublicationStatusEnum::PUBLISHED])->id,
        ]),
    };

    $product = $factory->create(['name' => $name, ...$productAttributes]);

    app(ProductPriceService::class)->updatePriceIndex($product->fresh());

    return $product;
}

it('reports the price bound of the requested productable type', function (): void {
    createSearchableProduct(100_000, 'Cheap Course');
    createSearchableProduct(300_000, 'Premium Course');
    createSearchableProduct(900_000, 'Seminar', ProductableEnum::SEMINAR);

    $this->getJson(route('api.v1.shop.search', ['productable_type' => ProductableEnum::COURSE->value]))
        ->assertOk()
        ->assertJsonCount(2, 'data.data')
        ->assertJsonPath('metadata.filters.price', ['min' => 100_000, 'max' => 300_000]);
});

it('reports the bound whenever the requested result types include products', function (array $resultTypes): void {
    createSearchableProduct(100_000, 'Cheap Course');

    $this->getJson(route('api.v1.shop.search', ['result_types' => $resultTypes]))
        ->assertOk()
        ->assertJsonPath('metadata.filters.price', ['min' => 100_000, 'max' => 100_000]);
})->with([
    'products only'         => [['product']],
    'products and articles' => [['product', 'blog_post']],
]);

it('keeps a separate catalogue bound per searched scope', function (): void {
    createSearchableProduct(100_000, 'Cheap Course');
    createSearchableProduct(900_000, 'Seminar', ProductableEnum::SEMINAR);

    $this->getJson(route('api.v1.shop.search', ['productable_type' => ProductableEnum::COURSE->value]))
        ->assertOk()
        ->assertJsonPath('metadata.filters.price', ['min' => 100_000, 'max' => 100_000]);

    $this->getJson(route('api.v1.shop.search'))
        ->assertOk()
        ->assertJsonPath('metadata.filters.price', ['min' => 100_000, 'max' => 900_000]);

    $this->getJson(route('api.v1.shop.search', ['productable_type' => ProductableEnum::SEMINAR->value]))
        ->assertOk()
        ->assertJsonPath('metadata.filters.price', ['min' => 900_000, 'max' => 900_000]);
});

it('reports the bound across every shopped type when the search names no productable type', function (): void {
    createSearchableProduct(100_000, 'Cheap Course');
    createSearchableProduct(700_000, 'Seminar', ProductableEnum::SEMINAR);
    createSearchableProduct(1_200_000, 'Digital Asset', ProductableEnum::DIGITAL_ASSET);

    $this->getJson(route('api.v1.shop.search'))
        ->assertOk()
        ->assertJsonCount(3, 'data.data')
        ->assertJsonPath('metadata.filters.price', ['min' => 100_000, 'max' => 1_200_000]);
});

it('never lets a bundle move the price bound', function (): void {
    createSearchableProduct(100_000, 'Cheap Course');
    createSearchableProduct(300_000, 'Premium Course');
    createSearchableProduct(9_000_000, 'Extreme Bundle', ProductableEnum::BUNDLE);
    createSearchableProduct(1_000, 'Bargain Bundle', ProductableEnum::BUNDLE);

    $this->getJson(route('api.v1.shop.search'))
        ->assertOk()
        ->assertJsonCount(2, 'data.data')
        ->assertJsonPath('metadata.filters.price', ['min' => 100_000, 'max' => 300_000]);
});

it('omits the price bound from a search that cannot return products', function (): void {
    createSearchableProduct(100_000, 'Cheap Course');
    BlogPost::factory()->create(['title' => 'A Published Article', 'status' => PublicationStatusEnum::PUBLISHED]);

    $this->getJson(route('api.v1.shop.search', ['q' => 'Article', 'result_types' => ['blog_post']]))
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.type', 'blog_post')
        ->assertJsonPath('metadata', []);
});

it('omits the price bound for a productable type the shop does not sell', function (): void {
    createSearchableProduct(9_000_000, 'Extreme Bundle', ProductableEnum::BUNDLE);

    $this->getJson(route('api.v1.shop.search', ['productable_type' => ProductableEnum::BUNDLE->value]))
        ->assertOk()
        ->assertJsonCount(0, 'data.data')
        ->assertJsonPath('metadata', []);
});

it('reports a bound for every shopped productable type', function (ProductableEnum $type): void {
    createSearchableProduct(100_000, 'Shopped Product', $type);

    $this->getJson(route('api.v1.shop.search', ['productable_type' => $type->value]))
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('metadata.filters.price', ['min' => 100_000, 'max' => 100_000]);
})->with([
    'courses'        => [ProductableEnum::COURSE],
    'seminars'       => [ProductableEnum::SEMINAR],
    'digital assets' => [ProductableEnum::DIGITAL_ASSET],
]);

it('reports the same catalogue bound whatever product filter or query the search carries', function (array $query, int $expectedCount): void {
    $category = Category::factory()->create(['slug' => 'budget-courses']);

    createSearchableProduct(100_000, 'Cheap Course')->categories()->attach($category->id);
    createSearchableProduct(300_000, 'Premium Course');

    $this->getJson(route('api.v1.shop.search', $query))
        ->assertOk()
        ->assertJsonCount($expectedCount, 'data.data')
        ->assertJsonPath('metadata.filters.price', ['min' => 100_000, 'max' => 300_000]);
})->with([
    'query string'    => [['q' => 'Cheap'], 1],
    'price filter'    => [['filter' => ['min_price' => '300000']], 1],
    'category filter' => [['filter' => ['category_slugs' => ['budget-courses']]], 1],
    'discount filter' => [['filter' => ['with_discounts' => '1']], 0],
])->group('price-bound');

it('ignores products the search cannot return when it computes the bound', function (): void {
    createSearchableProduct(150_000, 'Listed Course');

    createSearchableProduct(10_000, 'Hidden Course', productAttributes: ['is_visible' => false]);
    createSearchableProduct(20_000, 'Draft Product', productAttributes: ['status' => PublicationStatusEnum::DRAFT->value]);
    createSearchableProduct(30_000, 'Unpublished Course Product', productAttributes: [
        'productable_id' => Course::factory()->create(['status' => PublicationStatusEnum::DRAFT])->id,
    ]);

    $this->getJson(route('api.v1.shop.search'))
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('metadata.filters.price', ['min' => 150_000, 'max' => 150_000]);
});

it('reports null bound members when the searched catalogue has no priced product', function (): void {
    $this->getJson(route('api.v1.shop.search'))
        ->assertOk()
        ->assertJsonCount(0, 'data.data')
        ->assertJsonPath('metadata.filters.price', ['min' => null, 'max' => null]);
});
