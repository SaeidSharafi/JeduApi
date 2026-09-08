<?php

declare(strict_types=1);

use App\Actions\Admin\ProductDeliveryOption\SyncBundleCompositionAction;
use App\Actions\Admin\ProductDeliveryOption\UpdateProductDeliveryOptionAction;
use App\Data\Admin\ProductDeliveryOption\ProductDeliveryOptionUpdateData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Events\ProductAvailabilityCacheInvalidated;
use App\Events\ProductCacheInvalidated;
use App\Events\ProductSearchIndexInvalidated;
use App\Exceptions\BundleCompositionValidationException;
use App\Models\Bundle;
use App\Models\Course;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\assertDatabaseHas;

covers(UpdateProductDeliveryOptionAction::class);
covers(SyncBundleCompositionAction::class);
mutates(App\Services\BundleAvailabilityService::class);
mutates(App\Services\BundleAvailabilityPropagationService::class);

it('keeps a valid recomposition published and bumps the version without demanding review', function (): void {
    Event::fake([
        ProductCacheInvalidated::class,
        ProductAvailabilityCacheInvalidated::class,
        ProductSearchIndexInvalidated::class,
    ]);

    [, , $parent] = makeBundledProduct(500000);
    $first        = makeBundledComponent(600000);
    $second       = makeBundledComponent(500000);
    $parent->bundleComponents()->attach([
        $first->id  => ['allocation' => 200000],
        $second->id => ['allocation' => 300000],
    ]);

    app(UpdateProductDeliveryOptionAction::class)->handle(
        bundleUpdateData($parent, components: [
            ['product_delivery_option_id' => $first->id, 'allocation' => 150000],
            ['product_delivery_option_id' => $second->id, 'allocation' => 350000],
        ]),
        $parent,
    );

    $fresh = $parent->fresh();
    expect($fresh->status)->toBe(PublicationStatusEnum::PUBLISHED)
        ->and($fresh->bundle_review_required_at)->toBeNull()
        ->and($fresh->bundle_review_reasons)->toBeNull()
        ->and($fresh->composition_version)->toBe(2);
    Event::assertDispatched(ProductCacheInvalidated::class, fn (ProductCacheInvalidated $event): bool => $event->productId === $parent->product_id);
    Event::assertDispatched(ProductAvailabilityCacheInvalidated::class, fn (ProductAvailabilityCacheInvalidated $event): bool => $event->productIds === [$parent->product_id]);
    Event::assertDispatched(ProductSearchIndexInvalidated::class, fn (ProductSearchIndexInvalidated $event): bool => $event->productIds === [$parent->product_id]);
    assertDatabaseHas('bundle_components', [
        'bundle_product_delivery_option_id'    => $parent->id,
        'component_product_delivery_option_id' => $second->id,
        'allocation'                           => 350000,
    ]);
});

it('archives the parent Bundle and records base_price_changed when a component price changes via the action', function (): void {
    Event::fake([
        ProductCacheInvalidated::class,
        ProductAvailabilityCacheInvalidated::class,
        ProductSearchIndexInvalidated::class,
    ]);

    [, $product, $parent] = makeBundledProduct(400000);
    $component            = makeBundledComponent(400000);
    $parent->bundleComponents()->attach($component->id, ['allocation' => 400000]);

    app(UpdateProductDeliveryOptionAction::class)->handle(
        bundleUpdateData($component, price: 450000),
        $component,
    );

    $fresh = $parent->fresh();
    expect($fresh->status)->toBe(PublicationStatusEnum::PUBLISHED)
        ->and($fresh->bundle_review_required_at)->not->toBeNull()
        ->and($fresh->bundle_review_reasons)->toContain('base_price_changed')
        ->and($fresh->composition_version)->toBe(2);
    Event::assertDispatched(ProductAvailabilityCacheInvalidated::class, fn (ProductAvailabilityCacheInvalidated $event): bool => $event->productIds === [$product->id]);
    Event::assertDispatched(ProductSearchIndexInvalidated::class, fn (ProductSearchIndexInvalidated $event): bool => $event->productIds === [$product->id]);
    assertDatabaseHas('product_delivery_options', [
        'id'     => $parent->id,
        'status' => PublicationStatusEnum::PUBLISHED->value,
    ]);
});

it('normalizes bundle parent metadata and tracks composition changes even when the relation was not preloaded', function (): void {
    Event::fake([
        ProductCacheInvalidated::class,
        ProductAvailabilityCacheInvalidated::class,
        ProductSearchIndexInvalidated::class,
    ]);

    [, , $parent] = makeBundledProduct(500000);
    $first        = makeBundledComponent(500000);
    $second       = makeBundledComponent(500000);
    $parent->bundleComponents()->attach([
        $first->id  => ['allocation' => 200000],
        $second->id => ['allocation' => 300000],
    ]);

    $parent = ProductDeliveryOption::query()->findOrFail($parent->id);
    expect($parent->relationLoaded('bundleComponents'))->toBeFalse();

    app(UpdateProductDeliveryOptionAction::class)->handle(
        bundleUpdateData($parent, components: [
            ['product_delivery_option_id' => $first->id, 'allocation' => 250000],
            ['product_delivery_option_id' => $second->id, 'allocation' => 250000],
        ]),
        $parent,
    );

    $fresh = $parent->fresh();
    expect($fresh->fulfillment_type)->toBe(FulfillmentTypeEnum::COMPOSITE)
        ->and($fresh->delivery_method)->toBe(DeliveryMethodEnum::BUNDLE)
        ->and($fresh->details_json)->toBe([])
        ->and($fresh->composition_version)->toBe(2)
        ->and($fresh->status)->toBe(PublicationStatusEnum::PUBLISHED);

    Event::assertDispatched(ProductCacheInvalidated::class, fn (ProductCacheInvalidated $event): bool => $event->productId === $fresh->product_id);
    Event::assertDispatched(ProductAvailabilityCacheInvalidated::class, fn (ProductAvailabilityCacheInvalidated $event): bool => $event->productIds === [$fresh->product_id]);
    Event::assertDispatched(ProductSearchIndexInvalidated::class, fn (ProductSearchIndexInvalidated $event): bool => $event->productIds === [$fresh->product_id]);
});

it('invalidates bundle availability caches when the bundle index metadata changes without a status change', function (): void {
    Event::fake([
        ProductCacheInvalidated::class,
        ProductAvailabilityCacheInvalidated::class,
        ProductSearchIndexInvalidated::class,
    ]);

    [, , $parent] = makeBundledProduct(500000);
    $first        = makeBundledComponent(200000);
    $second       = makeBundledComponent(300000);
    $parent->bundleComponents()->attach([
        $first->id  => ['allocation' => 200000],
        $second->id => ['allocation' => 300000],
    ]);
    $parent->update(['available_from' => null]);

    $components = $parent->bundleComponents()->get()->map(fn (ProductDeliveryOption $option): array => [
        'product_delivery_option_id' => $option->id,
        'allocation'                 => (int) $option->pivot->allocation,
    ])->all();

    $data = new ProductDeliveryOptionUpdateData(
        name: (string) $parent->name,
        sku: (string) $parent->sku,
        price: $parent->price,
        status: $parent->status->value,
        details_json: $parent->details_json ?? [],
        teachers: [],
        capacity: $parent->capacity,
        is_prepayment_available: (bool) $parent->is_prepayment_available,
        prepayment_amount: $parent->prepayment_amount,
        is_featured: (bool) $parent->is_featured,
        featured_price: $parent->featured_price,
        featured_price_start_date: $parent->featured_price_start_date?->format('Y-m-d H:i:s'),
        featured_price_end_date: $parent->featured_price_end_date?->format('Y-m-d H:i:s'),
        registration_start_date: $parent->registration_start_date?->format('Y-m-d'),
        registration_end_date: $parent->registration_end_date?->format('Y-m-d'),
        available_from: now()->addDay()->format('Y-m-d'),
        available_to: $parent->available_to?->format('Y-m-d'),
        access_days: $parent->access_days,
        components: $components,
    );

    app(UpdateProductDeliveryOptionAction::class)->handle($data, $parent);

    $fresh = $parent->fresh();
    expect($fresh->available_from)->not->toBeNull()
        ->and($fresh->available_from?->format('Y-m-d'))->toBe(now()->addDay()->format('Y-m-d'))
        ->and($fresh->composition_version)->toBe(1);

    Event::assertDispatched(ProductCacheInvalidated::class, fn (ProductCacheInvalidated $event): bool => $event->productId === $fresh->product_id);
    Event::assertDispatched(ProductAvailabilityCacheInvalidated::class, fn (ProductAvailabilityCacheInvalidated $event): bool => $event->productIds === [$fresh->product_id]);
    Event::assertDispatched(ProductSearchIndexInvalidated::class, fn (ProductSearchIndexInvalidated $event): bool => $event->productIds === [$fresh->product_id]);
});

it('rejects bundle component payloads when the option is not a bundle', function (): void {
    $course  = Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
    $product = Product::factory()->withCourse($course)->create([
        'status'     => PublicationStatusEnum::PUBLISHED,
        'is_visible' => true,
    ]);
    $option = ProductDeliveryOption::factory()->create([
        'product_id' => $product->id,
        'price'      => 500000,
        'status'     => PublicationStatusEnum::PUBLISHED,
    ]);
    $component = makeBundledComponent(500000);

    expect(fn (): ProductDeliveryOption => app(UpdateProductDeliveryOptionAction::class)->handle(
        bundleUpdateData($option, components: [
            ['product_delivery_option_id' => $component->id, 'allocation' => 500000],
        ]),
        $option,
    ))->toThrow(BundleCompositionValidationException::class, __('messages.product.bundle_components_only_for_bundle'));
});

it('rejects repeated productables and nested bundle components in bundle composition updates', function (): void {
    config()->set('products.bundles.allow_repeated_productables', false);
    try {
        [, , $parent] = makeBundledProduct(500000);
        $firstCourse  = Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        $secondCourse = Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        $first        = Product::factory()->withCourse($firstCourse)->create([
            'status'     => PublicationStatusEnum::PUBLISHED,
            'is_visible' => true,
        ]);
        $second = Product::factory()->withCourse($firstCourse)->create([
            'status'     => PublicationStatusEnum::DRAFT,
            'is_visible' => true,
        ]);
        $firstOption = ProductDeliveryOption::factory()->create([
            'product_id' => $first->id,
            'price'      => 250000,
            'status'     => PublicationStatusEnum::PUBLISHED,
        ]);
        $secondOption = ProductDeliveryOption::factory()->create([
            'product_id' => $second->id,
            'price'      => 250000,
            'status'     => PublicationStatusEnum::PUBLISHED,
        ]);

        expect(fn (): ProductDeliveryOption => app(UpdateProductDeliveryOptionAction::class)->handle(
            bundleUpdateData($parent, components: [
                ['product_delivery_option_id' => $firstOption->id, 'allocation' => 250000],
                ['product_delivery_option_id' => $secondOption->id, 'allocation' => 250000],
            ]),
            $parent,
        ))->toThrow(BundleCompositionValidationException::class, __('messages.product.bundle_component_repeated_invalid'));

        [, , $bundleParent] = makeBundledProduct(500000);
        $nestedBundle       = Bundle::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        $nestedProduct      = Product::factory()->create([
            'productable_type' => 'bundle',
            'productable_id'   => $nestedBundle->id,
            'status'           => PublicationStatusEnum::PUBLISHED,
            'is_visible'       => true,
        ]);
        $nestedOption = ProductDeliveryOption::factory()->create([
            'product_id' => $nestedProduct->id,
            'price'      => 500000,
            'status'     => PublicationStatusEnum::PUBLISHED,
        ]);

        expect(fn (): ProductDeliveryOption => app(UpdateProductDeliveryOptionAction::class)->handle(
            bundleUpdateData($bundleParent, components: [
                ['product_delivery_option_id' => $nestedOption->id, 'allocation' => 500000],
            ]),
            $bundleParent,
        ))->toThrow(BundleCompositionValidationException::class, __('messages.product.bundle_component_nested_invalid'));
    } finally {
        config()->set('products.bundles.allow_repeated_productables', true);
    }
});

it('rejects bundle compositions whose allocations do not match the bundle total', function (): void {
    [, , $parent] = makeBundledProduct(500000);
    $first        = makeBundledComponent(200000);
    $second       = makeBundledComponent(300000);

    expect(fn (): ProductDeliveryOption => app(UpdateProductDeliveryOptionAction::class)->handle(
        bundleUpdateData($parent, components: [
            ['product_delivery_option_id' => $first->id, 'allocation' => 200000],
            ['product_delivery_option_id' => $second->id, 'allocation' => 200000],
        ]),
        $parent,
    ))->toThrow(BundleCompositionValidationException::class, __('messages.product.bundle_component_total_invalid'));
});

function makeBundledProduct(int $price): array
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

    return [$bundle, $product, $parent];
}

function makeBundledComponent(int $price): ProductDeliveryOption
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

function bundleUpdateData(
    ProductDeliveryOption $option,
    ?int $price = null,
    array $components = [],
    ?bool $isPrepaymentAvailable = null,
    ?int $prepaymentAmount = null,
): ProductDeliveryOptionUpdateData {
    return new ProductDeliveryOptionUpdateData(
        name: $option->name,
        sku: $option->sku,
        price: $price ?? $option->price,
        status: $option->status->value,
        details_json: $option->details_json,
        teachers: [],
        capacity: $option->capacity,
        is_prepayment_available: $isPrepaymentAvailable ?? $option->is_prepayment_available,
        prepayment_amount: $prepaymentAmount            ?? $option->prepayment_amount,
        is_featured: $option->is_featured,
        featured_price: $option->featured_price,
        featured_price_start_date: $option->featured_price_start_date,
        featured_price_end_date: $option->featured_price_end_date,
        registration_start_date: $option->registration_start_date,
        registration_end_date: $option->registration_end_date,
        available_from: $option->available_from,
        available_to: $option->available_to,
        access_days: $option->access_days,
        components: $components,
    );
}

it('normalizes prepayment fields off for a Bundle PDO', function (): void {
    [, , $parent] = makeBundledProduct(500000);
    $component    = makeBundledComponent(500000);
    $parent->bundleComponents()->attach($component->id, ['allocation' => 500000]);

    $updated = app(UpdateProductDeliveryOptionAction::class)->handle(
        bundleUpdateData(
            $parent,
            components: [['product_delivery_option_id' => $component->id, 'allocation' => 500000]],
            isPrepaymentAvailable: true,
            prepaymentAmount: 100000,
        ),
        $parent,
    );

    expect($updated->is_prepayment_available)->toBeFalse()
        ->and($updated->prepayment_amount)->toBeNull();
});

it('normalizes prepayment fields off for any composite PDO', function (): void {
    $composite = makeBundledComponent(500000);
    $composite->update([
        'fulfillment_type'        => FulfillmentTypeEnum::COMPOSITE,
        'is_prepayment_available' => true,
        'prepayment_amount'       => 100000,
    ]);

    $updated = app(UpdateProductDeliveryOptionAction::class)->handle(
        bundleUpdateData(
            $composite,
            isPrepaymentAvailable: true,
            prepaymentAmount: 100000,
        ),
        $composite,
    );

    expect($updated->is_prepayment_available)->toBeFalse()
        ->and($updated->prepayment_amount)->toBeNull();
});
