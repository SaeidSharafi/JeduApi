<?php

declare(strict_types=1);

use App\Actions\Admin\Discounts\UpdateDiscountPromotionStatusAction;
use App\Contracts\Cache\CacheStore;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Order\DiscountTypeEnum;
use App\Enums\System\CacheKey;
use App\Enums\TermStatusEnum;
use App\Jobs\UpdateProductAvailabilityJob;
use App\Jobs\UpdateProductPricingJob;
use App\Models\Course;
use App\Models\DiscountPromotion;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\ProductDeliveryOptionDiscountPrice;
use App\Models\Term;
use App\Services\BundleAvailabilityService;
use App\Services\Discounts\ProductDiscountIndexer;
use App\Services\ProductPriceService;

covers(
    UpdateProductPricingJob::class,
    UpdateProductAvailabilityJob::class,
    ProductDiscountIndexer::class,
    UpdateDiscountPromotionStatusAction::class,
);

$warmCatalogAndSearch = function (): void {
    $cache = app(CacheStore::class);
    $cache->put(CacheKey::GoodForStart, ['slug' => 'programming', 'limit' => 10], ['stale']);
    $cache->put(CacheKey::Search, ['hash' => 'query-hash'], ['stale']);
};

$assertCatalogAndSearchCleared = function (): void {
    $cache = app(CacheStore::class);
    expect($cache->get(CacheKey::GoodForStart, ['slug' => 'programming', 'limit' => 10]))->toBeNull()
        ->and($cache->get(CacheKey::Search, ['hash' => 'query-hash']))->toBeNull();
};

test('the pricing job clears the catalog and search groups', function () use ($warmCatalogAndSearch, $assertCatalogAndSearchCleared): void {
    $product = Product::withoutSyncingToSearch(fn (): Product => Product::factory()->create());
    ProductDeliveryOption::factory()->create(['product_id' => $product->id, 'price' => 250_000]);

    $warmCatalogAndSearch();
    (new UpdateProductPricingJob([$product->id]))->handle(app(ProductPriceService::class));

    $assertCatalogAndSearchCleared();
});

test('the availability job clears the catalog and search groups when a snapshot changes', function () use ($warmCatalogAndSearch, $assertCatalogAndSearchCleared): void {
    $course  = Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
    $term    = Term::factory()->create(['status' => TermStatusEnum::ACTIVE]);
    $product = Product::factory()->withCourse($course)->create(['term_id' => $term->id]);
    ProductDeliveryOption::factory()->create([
        'product_id' => $product->id,
        'status'     => PublicationStatusEnum::PUBLISHED,
        'capacity'   => 10,
    ]);

    $warmCatalogAndSearch();
    (new UpdateProductAvailabilityJob([$product->id]))->handle(
        app(CacheStore::class),
        app(BundleAvailabilityService::class),
    );

    $assertCatalogAndSearchCleared();
});

test('a full reindex with no active promotions still clears cached discounted prices', function () use ($warmCatalogAndSearch, $assertCatalogAndSearchCleared): void {
    expect(DiscountPromotion::query()->where('is_active', true)->exists())->toBeFalse();

    $warmCatalogAndSearch();
    app(ProductDiscountIndexer::class)->reIndexComplete();

    $assertCatalogAndSearchCleared();
});

test('toggling a promotion off reindexes the price immediately and clears the catalog, search and discount groups', function () use ($warmCatalogAndSearch, $assertCatalogAndSearchCleared): void {
    $promotion = DiscountPromotion::factory()->create([
        'is_active' => true,
        'type'      => DiscountTypeEnum::PRODUCT_SPECIFIC,
    ]);
    $option = ProductDeliveryOption::factory()->create(['price' => 100_000]);
    ProductDeliveryOptionDiscountPrice::query()->create([
        'product_delivery_option_id' => $option->id,
        'discount_promotion_id'      => $promotion->id,
        'discounted_price'           => 80_000,
        'starts_at'                  => null,
        'ends_at'                    => null,
    ]);

    $cache = app(CacheStore::class);
    $cache->put(CacheKey::DiscountHandlers, [], ['stale']);

    $warmCatalogAndSearch();
    $updated = app(UpdateDiscountPromotionStatusAction::class)->handle($promotion);

    expect($updated->is_active)->toBeFalse()
        ->and(ProductDeliveryOptionDiscountPrice::query()->where('discount_promotion_id', $promotion->id)->exists())->toBeFalse();

    $assertCatalogAndSearchCleared();
    expect($cache->get(CacheKey::DiscountHandlers))->toBeNull();
});
