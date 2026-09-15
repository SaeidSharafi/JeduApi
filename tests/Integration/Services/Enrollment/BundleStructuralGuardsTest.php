<?php

declare(strict_types=1);

use App\Actions\Shop\Student\GetEnrollmentDetailAction;
use App\Actions\Shop\Student\GetJoinUrlAction;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Enums\Product\ProductableEnum;
use App\Enums\ProvisioningStatusEnum;
use App\Enums\ProvisioningTriggerEnum;
use App\Exceptions\BundleStructuralInvariantException;
use App\Models\Bundle;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use App\Services\Enrollment\ProvisioningPlanResolver;
use App\Services\Provisioning\ProvisioningAttemptService;

use function Pest\Laravel\assertDatabaseCount;

function structuralBundleParentOption(): ProductDeliveryOption
{
    return ProductDeliveryOption::factory()->create([
        'product_id' => Product::factory()->create([
            'productable_type' => ProductableEnum::BUNDLE->value,
            'productable_id'   => Bundle::factory()->create(['status' => PublicationStatusEnum::PUBLISHED])->id,
        ])->id,
        'fulfillment_type' => FulfillmentTypeEnum::COMPOSITE,
        'delivery_method'  => DeliveryMethodEnum::BUNDLE,
        'details_json'     => [],
    ]);
}

/**
 * A parent Bundle PDO can never produce a real Enrollment through normal flow
 * (the EnrollmentObserver resolves its plan and the resolver rejects Bundle
 * PDOs). These tests fabricate one without events to prove every defensive
 * seam rejects it explicitly.
 */
function fabricatedParentEnrollment(ProductDeliveryOption $parent): Enrollment
{
    $customer = User::factory()->create();
    $order    = Order::factory()->create(['customer_id' => $customer->id]);
    $item     = OrderItem::factory()->create(['order_id' => $order->id]);

    return Enrollment::withoutEvents(fn (): Enrollment => Enrollment::factory()->create([
        'uuid'                       => (string) Illuminate\Support\Str::uuid7(),
        'order_id'                   => $order->id,
        'order_item_id'              => $item->id,
        'customer_id'                => $customer->id,
        'product_delivery_option_id' => $parent->id,
        'enrollment_status'          => EnrollmentStatusEnum::ACTIVE->value,
        'provisioning_status'        => ProvisioningStatusEnum::HEALTHY->value,
    ]));
}

it('rejects a Bundle parent PDO in the provisioning plan resolver', function (): void {
    $parent = structuralBundleParentOption();

    expect(fn () => app(ProvisioningPlanResolver::class)->resolve($parent))
        ->toThrow(BundleStructuralInvariantException::class);
});

it('rejects a fabricated Bundle parent Enrollment when a provisioning attempt is queued', function (): void {
    $parent     = structuralBundleParentOption();
    $enrollment = fabricatedParentEnrollment($parent);

    expect(fn () => app(ProvisioningAttemptService::class)->queue(
        $enrollment, ProvisioningTriggerEnum::PAYMENT,
    ))->toThrow(BundleStructuralInvariantException::class);

    assertDatabaseCount('provisioning_attempts', 0);
});

it('rejects a fabricated Bundle parent Enrollment at the live-session join seam', function (): void {
    $parent     = structuralBundleParentOption();
    $enrollment = fabricatedParentEnrollment($parent);

    expect(fn () => app(GetJoinUrlAction::class)->handle($enrollment))
        ->toThrow(BundleStructuralInvariantException::class);
});

it('rejects a fabricated Bundle parent Enrollment at the student enrollment detail seam', function (): void {
    $parent     = structuralBundleParentOption();
    $enrollment = fabricatedParentEnrollment($parent);

    expect(fn () => app(GetEnrollmentDetailAction::class)->handle($enrollment))
        ->toThrow(BundleStructuralInvariantException::class);
});
