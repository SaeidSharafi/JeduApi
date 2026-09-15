<?php

declare(strict_types=1);

use App\Actions\Admin\ProductDeliveryOption\ReviewBundleAction;
use App\Actions\Admin\ProductDeliveryOption\UpdateProductDeliveryOptionAction;
use App\Data\Admin\ProductDeliveryOption\ProductDeliveryOptionUpdateData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\PermissionEnum;
use App\Enums\Product\BundleReviewReasonEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Events\ProductAvailabilityCacheInvalidated;
use App\Events\ProductCacheInvalidated;
use App\Events\ProductSearchIndexInvalidated;
use App\Http\Controllers\Api\Admin\Product\ReviewBundleController;
use App\Models\Bundle;
use App\Models\Course;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use Illuminate\Support\Facades\Event;

uses(Tests\Support\Traits\AuthTestTrait::class);

covers(ReviewBundleAction::class);
covers(ReviewBundleController::class);

// Mutation survivors (equivalent by design, intentionally left untested):
// - ReviewBundleAction L26 RemoveNullSafeOperator: products.product_id is a
//   NOT NULL FK, so `product?->productable_type` can never hit a null product
//   and removing `?` is behaviorally identical for every reachable DB state.
// - ReviewBundleAction L32 RemoveMethodCall (load('bundleComponents')): the
//   relation is accessed right after in the map() closure, so removing the
//   eager load only changes the query count, never the outcome.

/**
 * Build a Bundle product with its parent COMPOSITE/BUNDLE delivery option.
 * FILE-LOCAL fixture (mirrors BundleAvailabilityServiceTest helpers) — do not
 * redefine makeBundleOffer/makeComponent.
 */
function makeBundledOptionFeature(int $price = 100000): ProductDeliveryOption
{
    $bundle  = Bundle::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
    $product = Product::factory()->create([
        'productable_type' => App\Enums\Product\ProductableEnum::BUNDLE->value,
        'productable_id'   => $bundle->id,
        'status'           => PublicationStatusEnum::PUBLISHED,
        'is_visible'       => true,
    ]);

    return ProductDeliveryOption::factory()->create([
        'product_id'          => $product->id,
        'fulfillment_type'    => FulfillmentTypeEnum::COMPOSITE,
        'delivery_method'     => DeliveryMethodEnum::BUNDLE,
        'price'               => $price,
        'capacity'            => null,
        'status'              => PublicationStatusEnum::PUBLISHED,
        'composition_version' => 1,
    ]);
}

function makeBundledComponentFeature(int $price = 100000): ProductDeliveryOption
{
    $course  = Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
    $product = Product::factory()->withCourse($course)->create([
        'status'     => PublicationStatusEnum::PUBLISHED,
        'is_visible' => true,
    ]);

    return ProductDeliveryOption::factory()->create([
        'product_id'     => $product->id,
        'price'          => $price,
        'capacity'       => null,
        'status'         => PublicationStatusEnum::PUBLISHED,
        'enrolled_count' => 0,
        'reserved_count' => 0,
    ]);
}

/**
 * Turn a PDO back into an update payload, optionally with a new price, so a
 * real component update can run through UpdateProductDeliveryOptionAction.
 */
function reviewUpdateDataFor(ProductDeliveryOption $pdo, ?int $price = null): ProductDeliveryOptionUpdateData
{
    return new ProductDeliveryOptionUpdateData(
        name: (string) $pdo->name,
        sku: (string) $pdo->sku,
        price: $price ?? (int) $pdo->price,
        status: $pdo->status->value,
        details_json: $pdo->details_json ?? [],
        teachers: [],
        capacity: $pdo->capacity,
        is_prepayment_available: (bool) $pdo->is_prepayment_available,
        prepayment_amount: $pdo->prepayment_amount,
        is_featured: (bool) $pdo->is_featured,
        featured_price: $pdo->featured_price,
        featured_price_start_date: $pdo->featured_price_start_date?->format('Y-m-d H:i:s'),
        featured_price_end_date: $pdo->featured_price_end_date?->format('Y-m-d H:i:s'),
        registration_start_date: $pdo->registration_start_date?->format('Y-m-d'),
        registration_end_date: $pdo->registration_end_date?->format('Y-m-d'),
        available_from: $pdo->available_from?->format('Y-m-d'),
        available_to: $pdo->available_to?->format('Y-m-d'),
        access_days: $pdo->access_days,
    );
}

it('reviews a flagged Bundle delivery option without changing its publication status', function (): void {
    $parent    = makeBundledOptionFeature();
    $component = makeBundledComponentFeature();
    $parent->bundleComponents()->attach($component->id, ['allocation' => 100000]);

    // Flag the Bundle for review the real way: a material component price change.
    app(UpdateProductDeliveryOptionAction::class)
        ->handle(reviewUpdateDataFor($component, $component->price + 1), $component);

    $flagged = $parent->fresh();
    expect($flagged->status)->toBe(PublicationStatusEnum::PUBLISHED)
        ->and((int) $flagged->composition_version)->toBe(2)
        ->and($flagged->bundle_review_required_at)->not->toBeNull()
        ->and($flagged->bundle_review_reasons)->toContain(BundleReviewReasonEnum::BASE_PRICE_CHANGED->value);

    $this->authorized_user([PermissionEnum::PRODUCT_DELIVERY_OPTION_UPDATE]);

    Event::fake([
        ProductCacheInvalidated::class,
        ProductAvailabilityCacheInvalidated::class,
        ProductSearchIndexInvalidated::class,
    ]);

    $response = $this->postJson(route('api.v1.admin.products.delivery-options.review', [
        'product'         => $parent->product_id,
        'delivery_option' => $parent->id,
    ]));

    $response->assertOk()
        ->assertJsonPath('data.id', $parent->id)
        ->assertJsonPath('data.status.value', PublicationStatusEnum::PUBLISHED->value)
        ->assertJsonPath('data.bundle_review_required_at', null)
        ->assertJsonPath('data.bundle_review_reasons', null)
        ->assertJsonPath('data.composition_version', 3);

    Event::assertDispatched(ProductCacheInvalidated::class, fn (ProductCacheInvalidated $event): bool => $event->productId === $parent->product_id);
    Event::assertDispatched(ProductAvailabilityCacheInvalidated::class, fn (ProductAvailabilityCacheInvalidated $event): bool => $event->productIds === [$parent->product_id]);
    Event::assertDispatched(ProductSearchIndexInvalidated::class, fn (ProductSearchIndexInvalidated $event): bool => $event->productIds === [$parent->product_id]);

    $this->assertDatabaseHas('product_delivery_options', [
        'id'                        => $parent->id,
        'status'                    => PublicationStatusEnum::PUBLISHED->value,
        'bundle_review_required_at' => null,
        'bundle_review_reasons'     => null,
        'composition_version'       => 3,
    ]);
});

it('does not clear the review flag when the component allocations no longer cover the Bundle price', function (): void {
    $parent    = makeBundledOptionFeature();
    $component = makeBundledComponentFeature();
    $parent->bundleComponents()->attach($component->id, ['allocation' => 100000]);

    app(UpdateProductDeliveryOptionAction::class)
        ->handle(reviewUpdateDataFor($component, $component->price + 1), $component);

    // Tamper with the composition after flagging: allocations now fall short
    // of the Bundle price, so composition validation must block the review.
    $parent->bundleComponents()->updateExistingPivot($component->id, ['allocation' => 50000]);

    $this->authorized_user([PermissionEnum::PRODUCT_DELIVERY_OPTION_UPDATE]);

    $response = $this->postJson(route('api.v1.admin.products.delivery-options.review', [
        'product'         => $parent->product_id,
        'delivery_option' => $parent->id,
    ]));

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['components']);

    $stillFlagged = $parent->fresh();
    expect($stillFlagged->status)->toBe(PublicationStatusEnum::PUBLISHED)
        ->and($stillFlagged->bundle_review_required_at)->not->toBeNull();
});

it('does not clear the review flag when a component is unpublished', function (): void {
    $parent    = makeBundledOptionFeature();
    $component = makeBundledComponentFeature();
    $parent->bundleComponents()->attach($component->id, ['allocation' => 100000]);

    app(UpdateProductDeliveryOptionAction::class)
        ->handle(reviewUpdateDataFor($component, $component->price + 1), $component);

    $component->update(['status' => PublicationStatusEnum::DRAFT->value]);

    $this->authorized_user([PermissionEnum::PRODUCT_DELIVERY_OPTION_UPDATE]);

    $response = $this->postJson(route('api.v1.admin.products.delivery-options.review', [
        'product'         => $parent->product_id,
        'delivery_option' => $parent->id,
    ]));

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['components']);

    $stillFlagged = $parent->fresh();
    expect($stillFlagged->status)->toBe(PublicationStatusEnum::PUBLISHED)
        ->and((int) $stillFlagged->composition_version)->toBe(2)
        ->and($stillFlagged->bundle_review_required_at)->not->toBeNull()
        ->and($stillFlagged->bundle_review_reasons)->toContain(BundleReviewReasonEnum::BASE_PRICE_CHANGED->value);
});

it('rejects reviewing a delivery option that is not backed by a Bundle', function (): void {
    $course  = Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
    $product = Product::factory()->withCourse($course)->create([
        'status'     => PublicationStatusEnum::PUBLISHED,
        'is_visible' => true,
    ]);
    $option = ProductDeliveryOption::factory()->create([
        'product_id' => $product->id,
        'capacity'   => null,
    ]);

    $this->authorized_user([PermissionEnum::PRODUCT_DELIVERY_OPTION_UPDATE]);

    $response = $this->postJson(route('api.v1.admin.products.delivery-options.review', [
        'product'         => $product->id,
        'delivery_option' => $option->id,
    ]));

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['delivery_option'])
        ->assertJsonPath('errors.delivery_option.0', __('messages.product.bundle_review_only_for_bundles'));

    expect($option->fresh()->status)->toBe(PublicationStatusEnum::PUBLISHED);
});

it('returns 404 when the delivery option belongs to a different product', function (): void {
    $option       = makeBundledOptionFeature();
    $otherProduct = Product::factory()->create();
    $this->authorized_user([PermissionEnum::PRODUCT_DELIVERY_OPTION_UPDATE]);

    $this->postJson(route('api.v1.admin.products.delivery-options.review', [
        'product'         => $otherProduct->id,
        'delivery_option' => $option->id,
    ]))->assertNotFound();
});

it('returns 403 when the staff member lacks the update permission', function (): void {
    $option = makeBundledOptionFeature();
    $this->unauthorized_user();

    $this->postJson(route('api.v1.admin.products.delivery-options.review', [
        'product'         => $option->product_id,
        'delivery_option' => $option->id,
    ]))->assertForbidden();
});

it('requires authentication', function (): void {
    $this->postJson('/api/v1/admin/products/1/delivery-options/1/review')
        ->assertUnauthorized();
});
