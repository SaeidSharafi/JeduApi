<?php

declare(strict_types=1);

use App\Actions\Admin\Order\ValidateNoDuplicatePurchasesAction;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\Bundle;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\Enrollment;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Seminar;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/** @return array{ProductDeliveryOption, ProductDeliveryOption} */
function makeEligibilityOptionsFor(Model $productable, ProductableEnum $type): array
{
    $product = Product::factory()->create([
        'productable_type' => $type->value,
        'productable_id'   => $productable->getKey(),
    ]);

    return [
        ProductDeliveryOption::factory()->create(['product_id' => $product->id]),
        ProductDeliveryOption::factory()->create(['product_id' => $product->id]),
    ];
}

/** @param array<int, ProductDeliveryOption> $components */
function makeEligibilityBundle(array $components): ProductDeliveryOption
{
    $bundle  = Bundle::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
    $product = Product::factory()->create([
        'productable_type' => ProductableEnum::BUNDLE->value,
        'productable_id'   => $bundle->id,
    ]);
    $option = ProductDeliveryOption::factory()->create([
        'product_id'       => $product->id,
        'fulfillment_type' => FulfillmentTypeEnum::COMPOSITE,
        'delivery_method'  => DeliveryMethodEnum::BUNDLE,
    ]);

    foreach ($components as $component) {
        $option->bundleComponents()->attach($component->id, ['allocation' => 0]);
    }

    return $option;
}

covers(ValidateNoDuplicatePurchasesAction::class);

it('rejects a Bundle when the Customer owns a component Productable through an alternate PDO', function (string $modelClass, ProductableEnum $type): void {
    $customer                        = User::factory()->create();
    [$ownedOption, $alternateOption] = makeEligibilityOptionsFor($modelClass::factory()->create(), $type);
    $bundleOption                    = makeEligibilityBundle([$alternateOption]);
    Enrollment::factory()->create([
        'customer_id'                => $customer->id,
        'product_delivery_option_id' => $ownedOption->id,
        'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
    ]);

    expect(fn () => resolve(ValidateNoDuplicatePurchasesAction::class)->handle($customer, collect([$bundleOption])))
        ->toThrow(ValidationException::class);
})->with([
    'Course'       => [Course::class, ProductableEnum::COURSE],
    'Seminar'      => [Seminar::class, ProductableEnum::SEMINAR],
    'DigitalAsset' => [DigitalAsset::class, ProductableEnum::DIGITAL_ASSET],
]);

it('rejects a standalone alternate PDO when a Bundle component Enrollment grants the Productable', function (): void {
    $customer                                = User::factory()->create();
    [$bundleComponent, $standaloneAlternate] = makeEligibilityOptionsFor(Course::factory()->create(), ProductableEnum::COURSE);
    Enrollment::factory()->create([
        'customer_id'                => $customer->id,
        'product_delivery_option_id' => $bundleComponent->id,
        'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
    ]);

    expect(fn () => resolve(ValidateNoDuplicatePurchasesAction::class)->handle($customer, collect([$standaloneAlternate])))
        ->toThrow(ValidationException::class);
});

it('rejects Bundle-versus-standalone and Bundle-versus-Bundle overlap in one selection', function (string $conflict): void {
    $customer                = User::factory()->create();
    [$component, $alternate] = makeEligibilityOptionsFor(Course::factory()->create(), ProductableEnum::COURSE);
    $firstBundle             = makeEligibilityBundle([$component]);
    $deliveryOptions         = $conflict === 'standalone'
        ? collect([$firstBundle, $alternate])
        : collect([$firstBundle, makeEligibilityBundle([$alternate])]);

    expect(fn () => resolve(ValidateNoDuplicatePurchasesAction::class)->handle($customer, $deliveryOptions))
        ->toThrow(ValidationException::class);
})->with(['standalone', 'Bundle']);

it('deduplicates repeated Productables inside one Bundle regardless of composition configuration', function (): void {
    config()->set('products.bundles.allow_repeated_productables', true);
    $customer            = User::factory()->create();
    [$first, $alternate] = makeEligibilityOptionsFor(Course::factory()->create(), ProductableEnum::COURSE);
    $bundleOption        = makeEligibilityBundle([$first, $alternate]);

    resolve(ValidateNoDuplicatePurchasesAction::class)->handle($customer, collect([$bundleOption]));

    expect(true)->toBeTrue();
});

it('allows unrelated standalone and Bundle content together', function (): void {
    $customer     = User::factory()->create();
    [$component]  = makeEligibilityOptionsFor(Course::factory()->create(), ProductableEnum::COURSE);
    [$standalone] = makeEligibilityOptionsFor(Seminar::factory()->create(), ProductableEnum::SEMINAR);
    $bundleOption = makeEligibilityBundle([$component]);

    resolve(ValidateNoDuplicatePurchasesAction::class)->handle($customer, collect([$bundleOption, $standalone]));

    expect(true)->toBeTrue();
});

it('keeps eligibility blocked until access revocation is complete', function (EnrollmentStatusEnum $status, bool $eligible): void {
    $customer = User::factory()->create();
    [$owned]  = makeEligibilityOptionsFor(Course::factory()->create(), ProductableEnum::COURSE);
    Enrollment::factory()->create([
        'customer_id'                => $customer->id,
        'product_delivery_option_id' => $owned->id,
        'enrollment_status'          => $status,
    ]);

    $check = fn () => resolve(ValidateNoDuplicatePurchasesAction::class)->handle($customer, collect([$owned]));

    if ($eligible) {
        $check();
        expect(true)->toBeTrue();

        return;
    }

    expect($check)->toThrow(ValidationException::class);
})->with([
    'active access'               => [EnrollmentStatusEnum::ACTIVE, false],
    'suspended/incomplete revoke' => [EnrollmentStatusEnum::SUSPENDED, false],
    'cancelled/complete revoke'   => [EnrollmentStatusEnum::CANCELLED, true],
]);
