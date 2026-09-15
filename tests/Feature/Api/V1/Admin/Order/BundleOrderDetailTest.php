<?php

declare(strict_types=1);

use App\Actions\Admin\Order\CreateOrderAction;
use App\Data\Admin\Order\OrderCreateData;
use App\Data\Admin\Order\OrderItemCreateData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\EnrollmentRevocationStatusEnum;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\PermissionEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Enums\Product\ProductableEnum;
use App\Enums\ProvisioningStatusEnum;
use App\Models\Bundle;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Traits\AuthTestTrait;

use function Pest\Laravel\getJson;

uses(AuthTestTrait::class);

function adminBundleFixture(): array
{
    $first = ProductDeliveryOption::factory()->create([
        'price'        => 120000, 'capacity' => 10, 'is_featured' => false,
        'details_json' => [],
    ]);
    $parent = ProductDeliveryOption::factory()->create([
        'product_id' => Product::factory()->create([
            'productable_type' => ProductableEnum::BUNDLE->value,
            'productable_id'   => Bundle::factory()->create([
                'status' => PublicationStatusEnum::PUBLISHED, 'full_name' => 'Admin Career Bundle',
            ])->id,
            'name' => 'Admin Career Bundle',
        ])->id,
        'name'             => 'Complete package', 'sku' => 'ADMIN-BUNDLE',
        'price'            => 100000, 'capacity' => null,
        'fulfillment_type' => FulfillmentTypeEnum::COMPOSITE,
        'delivery_method'  => DeliveryMethodEnum::BUNDLE,
        'details_json'     => [],
    ]);
    $parent->bundleComponents()->attach([
        $first->id => ['allocation' => 100000],
    ]);

    return [$parent, $first];
}

it('exposes grouped Bundle Purchases with aggregate and component statuses on the admin order detail', function (): void {
    Queue::fake([App\Jobs\Provisioning\ProvisionEnrollmentProviderJob::class]);
    $this->authorized_user([PermissionEnum::ORDER_VIEW->value]);

    [$parent, $component] = adminBundleFixture();
    $customer             = User::factory()->create();
    $order                = app(CreateOrderAction::class)->handle(new OrderCreateData(
        status: 'pending', customer_id: $customer->id,
        items: [new OrderItemCreateData($parent->id, 'full_payment', composition_version: 1)],
    ));

    // Simulate payment completion: paid order, every component item completed
    // and its Enrollment active with healthy provisioning.
    $order->payments()->create([
        'amount' => 100000, 'method' => PaymentMethodEnum::BANK_TRANSFER->value,
        'status' => PaymentStatusEnum::COMPLETED->value, 'customer_id' => $customer->id,
    ]);
    $order->update(['status' => OrderStatusEnum::COMPLETED->value]);
    foreach ($order->bundlePurchases->first()->components as $item) {
        $item->update(['status' => OrderItemStatusEnum::COMPLETED->value]);
        $item->enrollment()->update([
            'enrollment_status'   => EnrollmentStatusEnum::ACTIVE->value,
            'provisioning_status' => ProvisioningStatusEnum::HEALTHY->value,
        ]);
    }

    getJson(route('api.v1.admin.orders.show', ['order' => $order->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.type', 'bundle')
        ->assertJsonPath('data.items.0.bundle_name', 'Admin Career Bundle')
        ->assertJsonPath('data.items.0.status.value', 'active')
        ->assertJsonPath('data.items.0.components.0.product_delivery_option_id', $component->id)
        ->assertJsonPath('data.items.0.components.0.enrollment.enrollment_status.value', 'active')
        ->assertJsonPath('data.items.0.components.0.enrollment.provisioning_status.value', 'healthy')
        ->assertJsonPath('data.items.0.components.0.enrollment.revocation_status', null);

    expect(Order::query()->count())->toBe(1);
});

it('exposes the component enrollment revocation status on the admin order detail', function (): void {
    Queue::fake([App\Jobs\Provisioning\ProvisionEnrollmentProviderJob::class]);
    $this->authorized_user([PermissionEnum::ORDER_VIEW->value]);

    [$parent] = adminBundleFixture();
    $customer = User::factory()->create();
    $order    = app(CreateOrderAction::class)->handle(new OrderCreateData(
        status: 'pending', customer_id: $customer->id,
        items: [new OrderItemCreateData($parent->id, 'full_payment', composition_version: 1)],
    ));

    $order->payments()->create([
        'amount' => 100000, 'method' => PaymentMethodEnum::BANK_TRANSFER->value,
        'status' => PaymentStatusEnum::COMPLETED->value, 'customer_id' => $customer->id,
    ]);
    $order->update(['status' => OrderStatusEnum::COMPLETED->value]);

    // A refunded component whose external revocation is still staff work: the
    // aggregate status and the nested enrollment must both expose it.
    foreach ($order->bundlePurchases->first()->components as $item) {
        $item->update(['status' => OrderItemStatusEnum::REFUNDED->value]);
        $item->enrollment()->update([
            'enrollment_status' => EnrollmentStatusEnum::SUSPENDED->value,
            'revocation_status' => EnrollmentRevocationStatusEnum::MANUAL_ACTION_REQUIRED->value,
        ]);
    }

    getJson(route('api.v1.admin.orders.show', ['order' => $order->id]))
        ->assertOk()
        ->assertJsonPath('data.items.0.status.value', 'revocation_pending')
        ->assertJsonPath(
            'data.items.0.components.0.enrollment.revocation_status.value',
            EnrollmentRevocationStatusEnum::MANUAL_ACTION_REQUIRED->value,
        )
        ->assertJsonPath(
            'data.items.0.components.0.enrollment.revocation_status.label',
            'Manual Revocation Required',
        );
});
