<?php

declare(strict_types=1);

use App\Enums\EnrollmentStatusEnum;
use App\Enums\ProvisioningStatusEnum;
use Illuminate\Validation\ValidationException;

it('to array', function (): void {

    $enrollment = App\Models\Enrollment::factory()->create();

    $array = $enrollment->toArray();

    expect($array)->toBeArray()
        ->and($array)->toHaveKeys([
            'id',
            'order_id',
            'order_item_id',
            'customer_id',
            'product_delivery_option_id',
            'enrollment_status',
            'access_start_date',
            'access_end_date',
            'external_enrollment_id',
            'provisioning_data',
            'notes',
            'created_at',
            'updated_at',
        ]);
});

test('order relationship', function (): void {
    $enrollment = App\Models\Enrollment::factory()->create();

    $order = $enrollment->order;

    expect($order)->toBeInstanceOf(App\Models\Order::class)
        ->and($order->id)->toBe($enrollment->order_id);
});

test('order item relationship', function (): void {
    $enrollment = App\Models\Enrollment::factory()->create();

    $orderItem = $enrollment->orderItem;

    expect($orderItem)->toBeInstanceOf(App\Models\OrderItem::class)
        ->and($orderItem->id)->toBe($enrollment->order_item_id);
});

test('customer relationship', function (): void {
    $enrollment = App\Models\Enrollment::factory()->create();

    $customer = $enrollment->customer;

    expect($customer)->toBeInstanceOf(App\Models\User::class)
        ->and($customer->id)->toBe($enrollment->customer_id);
});

it('does not allow persisted activation before provisioning succeeds', function (): void {
    $enrollment = App\Models\Enrollment::factory()->create();
    $enrollment->update([
        'provisioning_plan' => [
            'version'   => 1,
            'providers' => [['provider' => 'moodle', 'applicable' => true, 'readiness' => 'ready']],
            'status'    => ProvisioningStatusEnum::READY->value,
        ],
        'provisioning_data' => [],
        'enrollment_status' => EnrollmentStatusEnum::PENDING_PROVISIONING,
    ]);

    expect(fn () => $enrollment->update(['enrollment_status' => EnrollmentStatusEnum::ACTIVE]))
        ->toThrow(ValidationException::class, 'cannot be activated');
});
