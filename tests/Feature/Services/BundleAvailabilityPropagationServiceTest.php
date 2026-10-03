<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\BundleReviewReasonEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Events\ProductAvailabilityCacheInvalidated;
use App\Events\ProductCacheInvalidated;
use App\Events\ProductSearchIndexInvalidated;
use App\Models\Bundle;
use App\Models\Course;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Services\BundleAvailabilityPropagationService;
use Illuminate\Support\Facades\Event;

covers(BundleAvailabilityPropagationService::class);

it('records every emitted review reason on the parent Bundle when components force a review', function (): void {
    $reasons = array_values(array_filter(
        BundleReviewReasonEnum::cases(),
        fn (BundleReviewReasonEnum $reason): bool => $reason !== BundleReviewReasonEnum::INVALID_COMPOSITION,
    ));

    expect($reasons)->not->toBeEmpty();

    foreach ($reasons as $reason) {
        Event::fake([
            ProductCacheInvalidated::class,
            ProductAvailabilityCacheInvalidated::class,
            ProductSearchIndexInvalidated::class,
        ]);

        [$product, $parent] = makeBundleParentPdo(300000);
        $component          = makeCourseComponentPdo(300000);
        $parent->bundleComponents()->attach($component->id, ['allocation' => 300000]);

        app(BundleAvailabilityPropagationService::class)
            ->requireReviewForComponentProducts([$component->product_id], [$reason->value]);

        $fresh = $parent->fresh();
        expect($fresh->status)->toBe(PublicationStatusEnum::PUBLISHED)
            ->and($fresh->bundle_review_required_at)->not->toBeNull()
            ->and($fresh->bundle_review_reasons)->toBe([$reason->value])
            ->and($fresh->composition_version)->toBe(2);
        Event::assertDispatched(ProductCacheInvalidated::class, fn (ProductCacheInvalidated $event): bool => $event->productId === $product->id);
        Event::assertDispatched(ProductAvailabilityCacheInvalidated::class, fn (ProductAvailabilityCacheInvalidated $event): bool => $event->productIds === [$product->id]);
        Event::assertDispatched(ProductSearchIndexInvalidated::class, fn (ProductSearchIndexInvalidated $event): bool => $event->productIds === [$product->id]);
    }
});

it('invalidates parent Bundle caches after a component change without archiving or flagging review', function (): void {
    Event::fake([
        ProductCacheInvalidated::class,
        ProductAvailabilityCacheInvalidated::class,
        ProductSearchIndexInvalidated::class,
    ]);

    [$product, $parent] = makeBundleParentPdo(300000);
    $component          = makeCourseComponentPdo(300000);
    $parent->bundleComponents()->attach($component->id, ['allocation' => 300000]);

    app(BundleAvailabilityPropagationService::class)->invalidateForComponentProducts([$component->product_id]);

    $fresh = $parent->fresh();
    expect($fresh->status)->toBe(PublicationStatusEnum::PUBLISHED)
        ->and($fresh->bundle_review_required_at)->toBeNull()
        ->and($fresh->bundle_review_reasons)->toBeNull()
        ->and($fresh->composition_version)->toBe(1);
    Event::assertDispatched(ProductCacheInvalidated::class, fn (ProductCacheInvalidated $event): bool => $event->productId === $product->id);
    Event::assertDispatched(ProductAvailabilityCacheInvalidated::class, fn (ProductAvailabilityCacheInvalidated $event): bool => $event->productIds === [$product->id]);
    Event::assertDispatched(ProductSearchIndexInvalidated::class, fn (ProductSearchIndexInvalidated $event): bool => $event->productIds === [$product->id]);
});

it('invalidates parent bundle products from direct component option ids and ignores empty input', function (): void {
    Event::fake([
        ProductCacheInvalidated::class,
        ProductAvailabilityCacheInvalidated::class,
        ProductSearchIndexInvalidated::class,
    ]);

    [$product, $parent] = makeBundleParentPdo(300000);
    $component          = makeCourseComponentPdo(300000);
    $parent->bundleComponents()->attach($component->id, ['allocation' => 300000]);

    app(BundleAvailabilityPropagationService::class)->invalidateForComponents([$component->id]);
    app(BundleAvailabilityPropagationService::class)->invalidateForComponents([]);

    Event::assertDispatched(ProductCacheInvalidated::class, fn (ProductCacheInvalidated $event): bool => $event->productId === $product->id);
    Event::assertDispatched(ProductAvailabilityCacheInvalidated::class, fn (ProductAvailabilityCacheInvalidated $event): bool => $event->productIds === [$product->id]);
    Event::assertDispatched(ProductSearchIndexInvalidated::class, fn (ProductSearchIndexInvalidated $event): bool => $event->productIds === [$product->id]);
});

it('requires review from component option ids and keeps bundle reasons stable across re-review', function (): void {
    Event::fake([
        ProductCacheInvalidated::class,
        ProductAvailabilityCacheInvalidated::class,
        ProductSearchIndexInvalidated::class,
    ]);

    [$product, $parent] = makeBundleParentPdo(300000);
    $component          = makeCourseComponentPdo(300000);
    $parent->bundleComponents()->attach($component->id, ['allocation' => 300000]);

    app(BundleAvailabilityPropagationService::class)->requireReview([$component->id], [BundleReviewReasonEnum::BASE_PRICE_CHANGED->value]);

    $fresh = $parent->fresh();
    expect($fresh->status)->toBe(PublicationStatusEnum::PUBLISHED)
        ->and($fresh->bundle_review_required_at)->not->toBeNull()
        ->and($fresh->bundle_review_reasons)->toBe([BundleReviewReasonEnum::BASE_PRICE_CHANGED->value])
        ->and($fresh->composition_version)->toBe(2);
    Event::assertDispatched(ProductCacheInvalidated::class, fn (ProductCacheInvalidated $event): bool => $event->productId === $product->id);
    Event::assertDispatched(ProductAvailabilityCacheInvalidated::class, fn (ProductAvailabilityCacheInvalidated $event): bool => $event->productIds === [$product->id]);
    Event::assertDispatched(ProductSearchIndexInvalidated::class, fn (ProductSearchIndexInvalidated $event): bool => $event->productIds === [$product->id]);
});

it('treats empty or parentless component input as a no-op', function (): void {
    Event::fake([
        ProductCacheInvalidated::class,
        ProductAvailabilityCacheInvalidated::class,
        ProductSearchIndexInvalidated::class,
    ]);

    $orphan = makeCourseComponentPdo(100000);

    app(BundleAvailabilityPropagationService::class)->requireReviewForComponentProducts([], [BundleReviewReasonEnum::BASE_PRICE_CHANGED->value]);
    app(BundleAvailabilityPropagationService::class)->invalidateForComponentProducts([]);
    app(BundleAvailabilityPropagationService::class)->requireReviewForComponentProducts([$orphan->product_id], [BundleReviewReasonEnum::BASE_PRICE_CHANGED->value]);
    app(BundleAvailabilityPropagationService::class)->invalidateForComponentProducts([$orphan->product_id]);

    Event::assertNotDispatched(ProductCacheInvalidated::class);
    Event::assertNotDispatched(ProductAvailabilityCacheInvalidated::class);
    Event::assertNotDispatched(ProductSearchIndexInvalidated::class);
});

it('invalidates bundle search indexes when a component product update invalidates parent bundle products', function (): void {
    Event::fake([
        ProductCacheInvalidated::class,
        ProductAvailabilityCacheInvalidated::class,
        ProductSearchIndexInvalidated::class,
    ]);

    [$product, $parent] = makeBundleParentPdo(300000);
    $component          = makeCourseComponentPdo(300000);
    $parent->bundleComponents()->attach($component->id, ['allocation' => 300000]);

    app(BundleAvailabilityPropagationService::class)->invalidateForComponentProducts([$component->product_id]);

    Event::assertDispatched(ProductCacheInvalidated::class, fn (ProductCacheInvalidated $event): bool => $event->productId === $product->id);
    Event::assertDispatched(ProductAvailabilityCacheInvalidated::class, fn (ProductAvailabilityCacheInvalidated $event): bool => $event->productIds === [$product->id]);
    Event::assertDispatched(ProductSearchIndexInvalidated::class, fn (ProductSearchIndexInvalidated $event): bool => $event->productIds === [$product->id]);
});

it('does not review or archive when a bundle parent lookup returns no matching parent option ids', function (): void {
    Event::fake([
        ProductCacheInvalidated::class,
        ProductAvailabilityCacheInvalidated::class,
        ProductSearchIndexInvalidated::class,
    ]);

    $orphan = makeCourseComponentPdo(300000);

    app(BundleAvailabilityPropagationService::class)->requireReviewForBundles([], [BundleReviewReasonEnum::BASE_PRICE_CHANGED->value]);
    app(BundleAvailabilityPropagationService::class)->invalidateForComponentProducts([$orphan->product_id]);

    Event::assertNotDispatched(ProductCacheInvalidated::class);
    Event::assertNotDispatched(ProductAvailabilityCacheInvalidated::class);
    Event::assertNotDispatched(ProductSearchIndexInvalidated::class);
});

it('records invalid composition only on the affected Bundle', function (): void {
    [, $invalidParent] = makeBundleParentPdo(300000);
    $invalidComponent  = makeCourseComponentPdo(200000);
    $invalidParent->bundleComponents()->attach($invalidComponent->id, ['allocation' => 300000]);

    [, $validParent] = makeBundleParentPdo(300000);
    $validComponent  = makeCourseComponentPdo(300000);
    $validParent->bundleComponents()->attach($validComponent->id, ['allocation' => 300000]);

    app(BundleAvailabilityPropagationService::class)->requireReviewForBundles(
        [$invalidParent->id, $validParent->id],
        [BundleReviewReasonEnum::BASE_PRICE_CHANGED->value],
    );

    expect($invalidParent->fresh()->bundle_review_reasons)
        ->toContain(BundleReviewReasonEnum::INVALID_COMPOSITION->value)
        ->and($validParent->fresh()->bundle_review_reasons)
        ->not->toContain(BundleReviewReasonEnum::INVALID_COMPOSITION->value);
});

function makeBundleParentPdo(int $price): array
{
    $bundle  = Bundle::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
    $product = Product::factory()->create([
        'productable_type' => 'bundle',
        'productable_id'   => $bundle->id,
        'status'           => PublicationStatusEnum::PUBLISHED,
        'is_visible'       => true,
    ]);
    $parent = ProductDeliveryOption::factory()->create([
        'product_id'       => $product->id,
        'fulfillment_type' => FulfillmentTypeEnum::COMPOSITE,
        'delivery_method'  => DeliveryMethodEnum::BUNDLE,
        'details_json'     => [],
        'capacity'         => null,
        'price'            => $price,
        'status'           => PublicationStatusEnum::PUBLISHED,
    ]);

    return [$product, $parent];
}

function makeCourseComponentPdo(int $price): ProductDeliveryOption
{
    $course  = Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
    $product = Product::factory()->withCourse($course)->create([
        'status'     => PublicationStatusEnum::PUBLISHED,
        'is_visible' => true,
    ]);

    return ProductDeliveryOption::factory()->create([
        'product_id' => $product->id,
        'price'      => $price,
        'capacity'   => null,
        'status'     => PublicationStatusEnum::PUBLISHED,
    ]);
}
