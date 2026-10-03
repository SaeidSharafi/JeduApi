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
use App\Enums\System\CacheKey;
use App\Models\Category;
use App\Models\Course;
use App\Models\Product;
use App\Models\ProductDeliveryOption;

covers(
    ArchiveProductAction::class,
    DeleteProductAction::class,
    DeleteProductDeliveryOptionAction::class,
    CreateCategoryAction::class,
    UpdateCategoryAction::class,
    DeleteCategoryAction::class,
    DeleteCourseAction::class,
    SetGoodForStartAction::class,
);

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
