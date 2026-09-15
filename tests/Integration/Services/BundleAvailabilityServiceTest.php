<?php

declare(strict_types=1);

use App\Actions\Admin\ProductDeliveryOption\ReviewBundleAction;
use App\Actions\Admin\ProductDeliveryOption\UpdateProductDeliveryOptionAction;
use App\Data\Admin\ProductDeliveryOption\ProductDeliveryOptionUpdateData;
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
use App\Services\BundleAvailabilityService;
use App\Services\ProductReservationService;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\assertDatabaseHas;

function makeBundleOffer(): array
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
        'capacity'         => null,
        'status'           => PublicationStatusEnum::PUBLISHED,
    ]);

    return [$bundle, $product, $parent];
}

function makeComponent(?int $capacity, int $enrolled = 0): ProductDeliveryOption
{
    $course  = Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
    $product = Product::factory()->withCourse($course)->create([
        'status'     => PublicationStatusEnum::PUBLISHED,
        'is_visible' => true,
    ]);

    return ProductDeliveryOption::factory()->create([
        'product_id'     => $product->id,
        'capacity'       => $capacity,
        'enrolled_count' => $enrolled,
        'reserved_count' => 0,
        'status'         => PublicationStatusEnum::PUBLISHED,
    ]);
}

covers(BundleAvailabilityService::class);

it('uses the least finite component capacity and ignores unlimited components', function (): void {
    [, , $parent] = makeBundleOffer();
    $limited      = makeComponent(5, 2);
    $unlimited    = makeComponent(null, 0);
    $parent->bundleComponents()->attach([
        $limited->id   => ['allocation' => 100000],
        $unlimited->id => ['allocation' => 100000],
    ]);

    expect($parent->fresh()->effectiveRemainingCapacity())->toBe(3);
});

it('recovers Bundle availability automatically after a temporary component constraint clears', function (): void {
    [, , $parent] = makeBundleOffer();
    $component    = makeComponent(5, 5);
    $parent->bundleComponents()->attach($component->id, ['allocation' => 100000]);
    $service = app(BundleAvailabilityService::class);

    expect($service->isAvailable($parent->fresh()))->toBeFalse();

    $component->update(['enrolled_count' => 4]);

    expect($service->isAvailable($parent->fresh()))->toBeTrue();
});

it('allows commercial review while a component is temporarily at capacity', function (): void {
    [, , $parent] = makeBundleOffer();
    $component    = makeComponent(1, 1);
    $parent->bundleComponents()->attach($component->id, ['allocation' => 100000]);

    expect(fn () => app(BundleAvailabilityService::class)->validateReview($parent->fresh()))
        ->not->toThrow(ValidationException::class);
});

it('allows commercial review while a component is temporarily outside its availability window', function (): void {
    [, , $parent] = makeBundleOffer();
    $component    = makeComponent(5);
    $parent->bundleComponents()->attach($component->id, ['allocation' => 100000]);
    $component->update(['available_from' => now()->addDay()]);

    expect(fn () => app(BundleAvailabilityService::class)->validateReview($parent->fresh()))
        ->not->toThrow(ValidationException::class);
});

it('keeps temporary registration and availability dates separate from commercial review', function (): void {
    [, , $parent] = makeBundleOffer();
    $component    = makeComponent(5);
    $parent->bundleComponents()->attach($component->id, ['allocation' => 100000]);

    $component->update(['registration_start_date' => now()->addDay()]);
    expect(fn () => app(BundleAvailabilityService::class)->validateReview($parent->fresh()))
        ->not->toThrow(ValidationException::class);

    $component->update(['registration_start_date' => null, 'registration_end_date' => now()->subDay()]);
    expect(fn () => app(BundleAvailabilityService::class)->validateReview($parent->fresh()))
        ->not->toThrow(ValidationException::class);

    $component->update(['registration_end_date' => null, 'available_from' => now()->addDay()]);
    expect(fn () => app(BundleAvailabilityService::class)->validateReview($parent->fresh()))
        ->not->toThrow(ValidationException::class);

    $component->update(['available_from' => null, 'available_to' => now()->subDay()]);
    expect(fn () => app(BundleAvailabilityService::class)->validateReview($parent->fresh()))
        ->not->toThrow(ValidationException::class);
});

it('rejects reservation for an empty Bundle without touching the parent PDO', function (): void {
    [, , $parent] = makeBundleOffer();

    expect(fn () => app(ProductReservationService::class)->reserve($parent->id, 1))
        ->toThrow(ValidationException::class);

    expect($parent->fresh()->reserved_count)->toBe(0);
});

it('keeps the containing Bundle status and records stable reasons for a material component change', function (): void {
    [, $product, $parent] = makeBundleOffer();
    $component            = makeComponent(5);
    $parent->bundleComponents()->attach($component->id, ['allocation' => 100000]);
    Event::fake([ProductAvailabilityCacheInvalidated::class]);

    $component->update(['price' => $component->price + 1]);
    app(BundleAvailabilityPropagationService::class)
        ->requireReview([$component->id], ['base_price_changed']);

    $parent->refresh();
    assertDatabaseHas('product_delivery_options', [
        'id'     => $parent->id,
        'status' => PublicationStatusEnum::PUBLISHED->value,
    ]);
    expect($parent->bundle_review_reasons)->toContain('base_price_changed')
        ->and($parent->composition_version)->toBe(2);
    Event::assertDispatched(ProductAvailabilityCacheInvalidated::class, fn (ProductAvailabilityCacheInvalidated $event): bool => $event->productIds === [$product->id]);
});

it('restores a Bundle to sale after an action-triggered material change is reviewed', function (): void {
    Event::fake([
        ProductCacheInvalidated::class,
        ProductAvailabilityCacheInvalidated::class,
        ProductSearchIndexInvalidated::class,
    ]);

    [, $product, $parent] = makeBundleOffer();
    $component            = makeComponent(null);
    $component->update(['price' => $parent->price]);
    $parent->bundleComponents()->attach($component->id, ['allocation' => $parent->price]);

    // Trigger the material change the real way: raise the component PDO price
    // through the update action so the require-review propagation fires.
    app(UpdateProductDeliveryOptionAction::class)->handle(
        optionUpdateData($component, price: $component->price + 1),
        $component->fresh(),
    );

    expect($parent->fresh()->status)->toBe(PublicationStatusEnum::PUBLISHED)
        ->and($parent->fresh()->bundle_review_required_at)->not->toBeNull()
        ->and($parent->fresh()->bundle_review_reasons)->toContain(BundleReviewReasonEnum::BASE_PRICE_CHANGED->value)
        ->and($parent->fresh()->composition_version)->toBe(2);

    $reviewed = app(ReviewBundleAction::class)->handle($parent->fresh());

    expect($reviewed->status)->toBe(PublicationStatusEnum::PUBLISHED)
        ->and($reviewed->bundle_review_required_at)->toBeNull()
        ->and($reviewed->bundle_review_reasons)->toBeNull()
        ->and($reviewed->composition_version)->toBe(3)
        ->and($reviewed->product_id)->toBe($product->id);
    Event::assertDispatched(ProductAvailabilityCacheInvalidated::class, fn (ProductAvailabilityCacheInvalidated $event): bool => $event->productIds === [$product->id]);
});

it('preserves a draft Bundle status through material review', function (): void {
    [, , $parent] = makeBundleOffer();
    $parent->update(['status' => PublicationStatusEnum::DRAFT]);
    $component = makeComponent(null);
    $component->update(['price' => $parent->price]);
    $parent->bundleComponents()->attach($component->id, ['allocation' => $parent->price]);

    app(BundleAvailabilityPropagationService::class)->requireReview(
        [$component->id],
        [BundleReviewReasonEnum::BASE_PRICE_CHANGED->value],
    );
    $reviewed = app(ReviewBundleAction::class)->handle($parent->fresh());

    expect($reviewed->status)->toBe(PublicationStatusEnum::DRAFT)
        ->and($reviewed->bundle_review_required_at)->toBeNull();
});

it('requires review only when provider identifiers change', function (): void {
    $service                = app(BundleAvailabilityService::class);
    $before                 = makeComponent(null);
    $providerIdentifierKeys = [
        'ims_course_code',
        'meeting_id',
        'moodle_course_id',
        'moodle_quiz_course_id',
        'nili_room_id',
        'room_id',
        'spot_id',
    ];

    foreach ($providerIdentifierKeys as $providerIdentifierKey) {
        $before->details_json           = [$providerIdentifierKey => 10, 'duration' => 60];
        $identifierChange               = $before->replicate();
        $identifierChange->details_json = [$providerIdentifierKey => 11, 'duration' => 60];

        expect($service->materialReasons($before, $identifierChange))
            ->toContain(BundleReviewReasonEnum::PROVIDER_IDENTIFIER_CHANGED->value);
    }

    $before->details_json             = ['moodle_course_id' => 10, 'duration' => 60];
    $presentationChange               = $before->replicate();
    $presentationChange->details_json = ['moodle_course_id' => 10, 'duration' => 90];

    expect($service->materialReasons($before, $presentationChange))
        ->not->toContain(BundleReviewReasonEnum::PROVIDER_IDENTIFIER_CHANGED->value);
});

it('identifies every material delivery option change reason', function (): void {
    $service = app(BundleAvailabilityService::class);
    $before  = makeComponent(null);

    $priceChange    = $before->replicate()->forceFill(['price' => $before->price + 1]);
    $deliveryChange = $before->replicate()->forceFill([
        'delivery_method' => $before->delivery_method === DeliveryMethodEnum::DIRECT_DOWNLOAD
            ? DeliveryMethodEnum::IN_PERSON
            : DeliveryMethodEnum::DIRECT_DOWNLOAD,
    ]);
    $accessChange  = $before->replicate()->forceFill(['access_days' => 30]);
    $productChange = $before->replicate()->forceFill(['product_id' => $before->product_id + 1]);
    $archiveChange = $before->replicate()->forceFill(['status' => PublicationStatusEnum::ARCHIVED]);

    expect($service->materialReasons($before, $priceChange))
        ->toContain(BundleReviewReasonEnum::BASE_PRICE_CHANGED->value)
        ->and($service->materialReasons($before, $deliveryChange))
        ->toContain(BundleReviewReasonEnum::DELIVERY_METHOD_CHANGED->value)
        ->and($service->materialReasons($before, $accessChange))
        ->toContain(BundleReviewReasonEnum::ACCESS_DURATION_CHANGED->value)
        ->and($service->materialReasons($before, $productChange))
        ->toContain(BundleReviewReasonEnum::PRODUCT_ASSOCIATION_CHANGED->value)
        ->and($service->materialReasons($before, $archiveChange))
        ->toContain(BundleReviewReasonEnum::COMPONENT_ARCHIVED->value);
});

it('rejects review for a non-Bundle delivery option with a field error', function (): void {
    $courseOption = makeComponent(null);

    $caught = null;
    try {
        app(ReviewBundleAction::class)->handle($courseOption);
    } catch (ValidationException $exception) {
        $caught = $exception;
    }

    expect($caught)->not->toBeNull()
        ->and($caught->errors())->toHaveKey('delivery_option')
        ->and($caught->errors()['delivery_option'])->toContain(__('messages.product.bundle_review_only_for_bundles'));
});

function optionUpdateData(
    ProductDeliveryOption $option,
    ?int $price = null,
    array $components = [],
): ProductDeliveryOptionUpdateData {
    return new ProductDeliveryOptionUpdateData(
        name: $option->name,
        sku: $option->sku,
        price: $price ?? $option->price,
        status: $option->status->value,
        details_json: $option->details_json,
        teachers: [],
        capacity: $option->capacity,
        is_prepayment_available: $option->is_prepayment_available,
        prepayment_amount: $option->prepayment_amount,
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
