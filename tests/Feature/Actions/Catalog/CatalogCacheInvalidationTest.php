<?php

declare(strict_types=1);

use App\Actions\Admin\Category\CreateCategoryAction;
use App\Actions\Admin\Category\DeleteCategoryAction;
use App\Actions\Admin\Category\SetGoodForStartAction;
use App\Actions\Admin\Category\UpdateCategoryAction;
use App\Actions\Admin\Course\DeleteCourseAction;
use App\Actions\Admin\Product\ArchiveProductAction;
use App\Actions\Admin\Product\DeleteProductAction;
use App\Actions\Admin\ProductDeliveryOption\DeleteProductDeliveryOptionAction;
use App\Contracts\Cache\CacheStore;
use App\Data\Admin\Category\CreateCategoryData;
use App\Data\Admin\Category\SetGoodForStartData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Enums\Product\ProductableEnum;
use App\Enums\System\CacheKey;
use App\Models\Category;
use App\Models\Course;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Services\CataloguePriceBoundService;
use App\Services\ProductPriceService;

mutates(
    ArchiveProductAction::class,
    DeleteProductAction::class,
    DeleteProductDeliveryOptionAction::class,
    CreateCategoryAction::class,
    UpdateCategoryAction::class,
    DeleteCategoryAction::class,
    DeleteCourseAction::class,
    SetGoodForStartAction::class,
);

/**
 * Create an available, published course product whose only delivery option costs
 * $price, and materialise its Purchase Price row.
 */
function createPriceBoundCourse(int $price, string $name): Product
{
    $product = Product::factory()
        ->withDeliveryOptions(1, [[
            'price'            => $price,
            'fulfillment_type' => FulfillmentTypeEnum::DIGITAL->value,
        ]])
        ->withCourse(Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]))
        ->create(['name' => $name]);

    app(ProductPriceService::class)->updatePriceIndex($product->fresh());

    return $product;
}

$warmListing = function (): void {
    app(CacheStore::class)->put(CacheKey::GoodForStart, ['slug' => 'programming', 'limit' => 10], ['stale']);
};

$assertListingCleared = function (): void {
    expect(app(CacheStore::class)->get(CacheKey::GoodForStart, ['slug' => 'programming', 'limit' => 10]))->toBeNull();
};

test('archiving a product clears the good-for-start listing', function () use ($warmListing, $assertListingCleared): void {
    Event::fake([
        App\Events\ProductCacheInvalidated::class,
        App\Events\ProductAvailabilityCacheInvalidated::class,
        App\Events\ProductSearchIndexInvalidated::class,
    ]);
    $product = Product::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);

    $warmListing();
    app(ArchiveProductAction::class)->handle($product);

    $assertListingCleared();
});

test('deleting a product clears the good-for-start listing', function () use ($warmListing, $assertListingCleared): void {
    $product = Product::factory()->create();

    $warmListing();
    app(DeleteProductAction::class)->handle($product);

    $assertListingCleared();
});

test('deleting a delivery option clears the good-for-start listing', function () use ($warmListing, $assertListingCleared): void {
    Event::fake([
        App\Events\ProductCacheInvalidated::class,
        App\Events\ProductAvailabilityCacheInvalidated::class,
        App\Events\ProductSearchIndexInvalidated::class,
    ]);
    $option = ProductDeliveryOption::factory()->create();

    $warmListing();
    app(DeleteProductDeliveryOptionAction::class)->handle($option);

    $assertListingCleared();
});

test('category writes clear the good-for-start listing', function () use ($warmListing, $assertListingCleared): void {
    $categoryData = fn (string $slug): CreateCategoryData => CreateCategoryData::from([
        'name'             => 'Category '.$slug,
        'slug'             => $slug,
        'status'           => PublicationStatusEnum::PUBLISHED->value,
        'parent_id'        => null,
        'description'      => null,
        'color_scheme'     => null,
        'meta_title'       => 'Meta title',
        'meta_description' => 'Meta description that is long enough to satisfy the validation rule of the DTO.',
        'meta_keywords'    => null,
        'properties'       => null,
        'additional_info'  => null,
        'media'            => [],
    ]);

    $warmListing();
    app(CreateCategoryAction::class)->handle($categoryData('category-a'));
    $assertListingCleared();

    $category = Category::query()->where('slug', 'category-a')->firstOrFail();

    $warmListing();
    app(UpdateCategoryAction::class)->handle($categoryData('category-b'), $category);
    $assertListingCleared();

    $warmListing();
    app(DeleteCategoryAction::class)->handle($category);
    $assertListingCleared();
});

test('deleting a course clears the good-for-start listing', function () use ($warmListing, $assertListingCleared): void {
    $course = Course::factory()->create();

    $warmListing();
    app(DeleteCourseAction::class)->handle($course);

    $assertListingCleared();
});

test('changing the good-for-start flag clears the good-for-start listing', function () use ($warmListing, $assertListingCleared): void {
    $category = Category::factory()->create();
    $course   = Course::factory()->create();
    $category->courses()->attach($course);

    $warmListing();
    app(SetGoodForStartAction::class)->handle($category, SetGoodForStartData::from([
        'course_ids'     => [$course->id],
        'good_for_start' => true,
    ]));

    $assertListingCleared();
});

test('a catalog write makes the next read regenerate the course price bound', function (): void {
    $bound = fn (): array => app(CataloguePriceBoundService::class)->forCatalogue(ProductableEnum::COURSE);

    expect($bound())->toBe(['min' => null, 'max' => null]);

    // Written straight through the factory and the price index, so no catalog write
    // path runs and the bound the call above cached stays in place.
    createPriceBoundCourse(150_000, 'Added While The Bound Was Cached');

    expect($bound())->toBe(['min' => null, 'max' => null]);

    Event::fake([
        App\Events\ProductCacheInvalidated::class,
        App\Events\ProductAvailabilityCacheInvalidated::class,
        App\Events\ProductSearchIndexInvalidated::class,
    ]);
    $product = Product::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);

    app(ArchiveProductAction::class)->handle($product);

    expect($bound())->toBe(['min' => 150_000, 'max' => 150_000]);
});

test('keeps a category price bound in its own cache entry', function (): void {
    $category = Category::factory()->create(['slug' => 'scoped-category']);

    $catalogue     = fn (): array => app(CataloguePriceBoundService::class)->forCatalogue(ProductableEnum::COURSE);
    $categoryBound = fn (): array => app(CataloguePriceBoundService::class)->forCategoryCatalogue($category, ProductableEnum::COURSE);

    expect($catalogue())->toBe(['min' => null, 'max' => null]);

    // Written behind the gateway: the catalogue keeps its cached empty bound, while the
    // category entry is a different key and computes the new product on its next read.
    createPriceBoundCourse(150_000, 'Added Behind The Gateway')->categories()->attach($category->id);

    expect($catalogue())->toBe(['min' => null, 'max' => null])
        ->and($categoryBound())->toBe(['min' => 150_000, 'max' => 150_000]);

    Event::fake([
        App\Events\ProductCacheInvalidated::class,
        App\Events\ProductAvailabilityCacheInvalidated::class,
        App\Events\ProductSearchIndexInvalidated::class,
    ]);
    $product = Product::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);

    app(ArchiveProductAction::class)->handle($product);

    expect($catalogue())->toBe(['min' => 150_000, 'max' => 150_000])
        ->and($categoryBound())->toBe(['min' => 150_000, 'max' => 150_000]);
});
