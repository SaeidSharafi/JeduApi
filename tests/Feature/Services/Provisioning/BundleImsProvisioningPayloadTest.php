<?php

declare(strict_types=1);

use App\Enums\EnrollmentStatusEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Models\BundlePurchase;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use App\Services\Integrations\ImsService;
use App\Services\Provisioning\Providers\ImsProvisioningProvider;

/**
 * IMS must receive the immutable per-component snapshot for a Bundle
 * component: allocation as actual paid amount, base price minus allocation as
 * manual discount, no discount code, and the Bundle identity + JeduShop order
 * number in the note.
 */
function imsBundleComponentEnrollment(int $price, int $allocation): array
{
    $customer = User::factory()->create();
    $option   = ProductDeliveryOption::factory()->create([
        'price'        => $price,
        'details_json' => ['ims_course_code' => 'IMS-BUNDLE-1'],
    ]);
    $order = Order::factory()->create([
        'customer_id'         => $customer->id,
        'grand_total'         => $allocation,
        'applied_coupon_code' => 'CODE-X', // must never reach a Bundle component
    ]);
    $purchase = BundlePurchase::query()->create([
        'order_id'                   => $order->id,
        'product_delivery_option_id' => ProductDeliveryOption::factory()->create()->id,
        'bundle_name'                => 'Career Bundle', 'product_name' => 'Career', 'name' => 'Complete package',
        'sku'                        => 'BUNDLE-SKU-1', 'base_value' => $price, 'selling_price' => $allocation,
        'composition_version'        => 1, 'checkout_status' => 'pending', 'product_data_snapshot_json' => [],
    ]);
    $item = OrderItem::factory()->create([
        'order_id'                   => $order->id,
        'bundle_purchase_id'         => $purchase->id,
        'product_delivery_option_id' => $option->id,
        'price'                      => $price,
        'total'                      => $allocation,
        'pricing_metadata'           => [
            'original_price'          => $price,
            'base_price_amount'       => $price,
            'paid_amount'             => $allocation,
            'product_discount_amount' => $price - $allocation,
            'cart_discount_amount'    => 0,
            'total_discount_amount'   => $price - $allocation,
            'discount_type'           => 'bundle',
            'discount_amount'         => $price - $allocation,
        ],
    ]);
    $enrollment = Enrollment::factory()->create([
        'order_id'                   => $order->id,
        'order_item_id'              => $item->id,
        'customer_id'                => $customer->id,
        'product_delivery_option_id' => $option->id,
        'enrollment_status'          => EnrollmentStatusEnum::ACTIVE->value,
    ]);
    Payment::factory()->create([
        'order_id'    => $order->id,
        'customer_id' => $customer->id,
        'amount'      => $allocation,
        'status'      => PaymentStatusEnum::COMPLETED,
    ]);

    return [$enrollment, $order];
}

it('sends the component allocation and manual discount to IMS with no discount code and the Bundle note', function (): void {
    [$enrollment, $order] = imsBundleComponentEnrollment(price: 100000, allocation: 60000);

    $service = $this->mock(ImsService::class);
    $service->shouldReceive('isEnabled')->andReturnTrue();
    $service->shouldReceive('assertConfigured');
    $service->shouldReceive('storeStudent')->andReturn(['data' => ['student_id' => 7]]);
    $service->shouldReceive('storeEnrollment')->once()->withArgs(function ($user, array $payload) use ($order): bool {
        return $payload['payment']['amount']          === 60000
            && $payload['payment']['discount_type']   === 'manual'
            && $payload['payment']['discount_amount'] === 40000
            && $payload['payment']['discount_code']   === null
            && str_contains($payload['note'], 'Career Bundle')
            && str_contains($payload['note'], 'BUNDLE-SKU-1')
            && str_contains($payload['note'], (string) $order->increment_id);
    })->andReturn(['data' => ['enrollment_id' => 9]]);

    expect((new ImsProvisioningProvider($service))->provision($enrollment))
        ->toMatchArray(['course_code' => 'IMS-BUNDLE-1', 'ims_enrollment_id' => 9]);
});

it('sends a zero allocation as zero paid and the full base price as manual discount', function (): void {
    [$enrollment, $order] = imsBundleComponentEnrollment(price: 100000, allocation: 0);

    $service = $this->mock(ImsService::class);
    $service->shouldReceive('isEnabled')->andReturnTrue();
    $service->shouldReceive('assertConfigured');
    $service->shouldReceive('storeStudent')->andReturn(['data' => ['student_id' => 8]]);
    $service->shouldReceive('storeEnrollment')->once()->withArgs(function ($user, array $payload): bool {
        return $payload['payment']['amount']          === 0
            && $payload['payment']['discount_type']   === 'manual'
            && $payload['payment']['discount_amount'] === 100000
            && $payload['payment']['discount_code']   === null
            && str_contains($payload['note'], 'Career Bundle')
            && str_contains($payload['note'], 'BUNDLE-SKU-1');
    })->andReturn(['data' => ['enrollment_id' => 10]]);

    expect((new ImsProvisioningProvider($service))->provision($enrollment))
        ->toMatchArray(['course_code' => 'IMS-BUNDLE-1']);
});
