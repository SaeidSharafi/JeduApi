<?php

declare(strict_types=1);

use App\Actions\Admin\Order\ValidateNoDuplicatePurchasesAction;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\Bundle;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\Support\Traits\AuthTestTrait;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\postJson;

uses(AuthTestTrait::class);

/** @return array{ProductDeliveryOption, ProductDeliveryOption} */
function makeCartEligibilityCourseOptions(): array
{
    $course  = Course::factory()->create();
    $product = Product::factory()->create([
        'productable_type' => ProductableEnum::COURSE->value,
        'productable_id'   => $course->id,
    ]);

    return [
        ProductDeliveryOption::factory()->create([
            'product_id'   => $product->id,
            'status'       => PublicationStatusEnum::PUBLISHED,
            'available_to' => null,
        ]),
        ProductDeliveryOption::factory()->create([
            'product_id'   => $product->id,
            'status'       => PublicationStatusEnum::PUBLISHED,
            'available_to' => null,
        ]),
    ];
}

function makeCartEligibilityBundle(ProductDeliveryOption $component): ProductDeliveryOption
{
    $bundle  = Bundle::factory()->create();
    $product = Product::factory()->create([
        'productable_type' => ProductableEnum::BUNDLE->value,
        'productable_id'   => $bundle->id,
    ]);
    $bundleOption = ProductDeliveryOption::factory()->create([
        'product_id'       => $product->id,
        'fulfillment_type' => FulfillmentTypeEnum::COMPOSITE,
        'delivery_method'  => DeliveryMethodEnum::BUNDLE,
        'price'            => 0,
        'available_to'     => null,
        'status'           => PublicationStatusEnum::PUBLISHED,
    ]);
    $bundleOption->bundleComponents()->attach($component->id, ['allocation' => 0]);

    return $bundleOption;
}

covers(ValidateNoDuplicatePurchasesAction::class);

it('returns 422 before adding a Bundle whose component Productable the Customer owns', function (): void {
    $customer                  = User::factory()->create(['uuid' => (string) Str::uuid7()]);
    [$owned, $bundleComponent] = makeCartEligibilityCourseOptions();
    $bundleOption              = makeCartEligibilityBundle($bundleComponent);
    Enrollment::factory()->create([
        'customer_id'                => $customer->id,
        'product_delivery_option_id' => $owned->id,
        'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
    ]);
    $this->customer($customer);

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $bundleOption->uuid,
        'quantity'                     => 1,
    ])->assertUnprocessable()->assertJsonValidationErrors(['items']);

    assertDatabaseCount('cart_items', 0);
});

it('returns 422 before adding a standalone item that overlaps a Bundle already in a guest cart', function (): void {
    [$component, $standaloneAlternate] = makeCartEligibilityCourseOptions();
    $bundleOption                      = makeCartEligibilityBundle($component);

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $bundleOption->uuid,
        'quantity'                     => 1,
    ])->assertOk();

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $standaloneAlternate->uuid,
        'quantity'                     => 1,
    ])->assertUnprocessable()->assertJsonValidationErrors(['items']);

    assertDatabaseCount('cart_items', 1);
});

it('returns 422 for Bundle prepayment even if stale persisted flags claim it is available', function (): void {
    [$component]  = makeCartEligibilityCourseOptions();
    $bundleOption = makeCartEligibilityBundle($component);
    $bundleOption->update([
        'is_prepayment_available' => true,
        'prepayment_amount'       => 1,
    ]);

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $bundleOption->uuid,
        'quantity'                     => 1,
        'payment_type'                 => OrderItemPaymentTypeEnum::PRE_PAYMENT->value,
    ])->assertUnprocessable()->assertJsonValidationErrors(['payment_type']);

    assertDatabaseCount('cart_items', 0);
});

it('returns 422 for prepayment on any composite delivery option', function (): void {
    [$component] = makeCartEligibilityCourseOptions();
    $component->update([
        'fulfillment_type'        => FulfillmentTypeEnum::COMPOSITE,
        'is_prepayment_available' => true,
        'prepayment_amount'       => 1,
    ]);

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $component->uuid,
        'quantity'                     => 1,
        'payment_type'                 => OrderItemPaymentTypeEnum::PRE_PAYMENT->value,
    ])->assertUnprocessable()->assertJsonValidationErrors(['payment_type']);

    assertDatabaseCount('cart_items', 0);
});
