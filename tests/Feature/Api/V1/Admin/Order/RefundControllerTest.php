<?php

declare(strict_types=1);

use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Order\RefundStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Models\Order;
use App\Models\Refund;

use function Pest\Laravel\assertDatabaseHas;

uses(Tests\Support\Traits\AuthTestTrait::class);
describe('RefundController', function (): void {

    it('should return a list of refunds', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::REFUND_VIEW_ANY]);
        $order = Order::factory()->withCalculatedTotals(
            [
                [
                    'total'  => 50000,
                    'status' => OrderItemStatusEnum::COMPLETED->value,
                ],
            ]
        )->create();
        $order->payments()->create([
            'customer_id' => $order->customer_id,
            'method'      => PaymentMethodEnum::BANK_TRANSFER,
            'amount'      => 50000,
            'status'      => 'completed',
        ]);

        $orderItem = $order->items->first();
        Refund::factory()->create([
            'order_item_id' => $orderItem->id,
            'status'        => RefundStatusEnum::CANCELLED,
        ]);
        Refund::factory()->create([
            'order_item_id' => $orderItem->id,
            'status'        => RefundStatusEnum::FAILED,
        ]);
        Refund::factory()->create([
            'order_item_id' => $orderItem->id,
            'status'        => RefundStatusEnum::COMPLETED,
        ]);
        Refund::factory()->create([
            'order_item_id' => App\Models\OrderItem::factory()->create(),
            'status'        => RefundStatusEnum::COMPLETED,
        ]);
        $response = $this->getJson(route('api.v1.admin.refund.index', ['orderItem' => $orderItem->id]));
        $response->assertOk()
            ->assertJsonCount(3, 'data.data')
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'deduction_amount',
                            'transaction_details' => [
                                'receiver_name',
                                'card_number',
                                'iban_number',
                                'tracking_code',
                            ],
                            'status',
                            'admin_notes',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                ],
            ]);
    });

    it('should create a refund', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::REFUND_CREATE]);
        $order = Order::factory()->withCalculatedTotals(
            [
                [
                    'total'  => 50000,
                    'price'  => 50000,
                    'status' => OrderItemStatusEnum::COMPLETED->value,
                ],
            ]
        )->create();
        $order->payments()->create([
            'customer_id' => $order->customer_id,
            'method'      => PaymentMethodEnum::BANK_TRANSFER,
            'amount'      => 50000,
            'status'      => PaymentStatusEnum::COMPLETED,
        ]);

        $orderItem = $order->items()->first();
        App\Models\Enrollment::factory()
            ->create([
                'order_id'                   => $order->id,
                'order_item_id'              => $orderItem->id,
                'customer_id'                => $order->customer_id,
                'product_delivery_option_id' => $orderItem->product_delivery_option_id,
                'enrollment_status'          => App\Enums\EnrollmentStatusEnum::ACTIVE->value,
            ]);
        $data = [
            'deduction_percent'   => 20,
            'status'              => RefundStatusEnum::COMPLETED->value,
            'transaction_details' => [
                'receiver_name' => 'John Doe',
                'card_number'   => '1234567890123456',
                'iban_number'   => 'IR123456789012345678901234',
                'tracking_code' => 'TRK1234567890',
            ],
        ];

        $response = $this->postJson(route('api.v1.admin.refund.store', ['orderItem' => $orderItem->id]), $data);
        $response->assertCreated()
            ->assertJson(function (Illuminate\Testing\Fluent\AssertableJson $json): void {
                $json
                    ->where('data.deduction_amount', 10000) // 20% of 50000
                    ->where('data.status', [
                        'value' => RefundStatusEnum::COMPLETED->value,
                        'label' => RefundStatusEnum::COMPLETED->translate(),
                    ])
                    ->where('data.transaction_details', [
                        'receiver_name' => 'John Doe',
                        'card_number'   => '1234567890123456',
                        'iban_number'   => 'IR123456789012345678901234',
                        'tracking_code' => 'TRK1234567890',
                    ])
                    ->etc();
            });

        assertDatabaseHas('refunds', [
            'order_item_id'                      => $orderItem->id,
            'deduction_amount'                   => 10000,
            'status'                             => RefundStatusEnum::COMPLETED->value,
            'transaction_details->receiver_name' => 'John Doe',
            'transaction_details->card_number'   => '1234567890123456',
        ]);
        $orderItem->refresh();
        assertDatabaseHas('order_items', [
            'id'             => $orderItem->id,
            'status'         => OrderItemStatusEnum::REFUNDED->value,
            'total_refunded' => 40000, // 50000 - 10000
        ]);
        assertDatabaseHas('orders', [
            'id'     => $order->id,
            'status' => OrderStatusEnum::REFUNDED->value,
        ]);
        assertDatabaseHas('enrollments', [
            'order_id'          => $order->id,
            'order_item_id'     => $orderItem->id,
            'enrollment_status' => App\Enums\EnrollmentStatusEnum::CANCELLED->value,
        ]);
    });

    it('should not create a refund with invalid data',
        function (?int $deductionAmount, ?int $deductionPercent, string $field): void {
            $this->authorized_user([App\Enums\PermissionEnum::REFUND_CREATE]);
            $order = Order::factory()->withCalculatedTotals(
                [
                    [
                        'total'  => 50000,
                        'price'  => 50000,
                        'status' => OrderItemStatusEnum::COMPLETED->value,
                    ],
                ]
            )->create();
            $order->payments()->create([
                'customer_id' => $order->customer_id,
                'method'      => PaymentMethodEnum::BANK_TRANSFER,
                'amount'      => 50000,
                'status'      => PaymentStatusEnum::COMPLETED,
            ]);

            $orderItem = $order->items()->first();

            $data = [
                'deduction_amount'    => $deductionAmount,
                'deduction_percent'   => $deductionPercent,
                'status'              => RefundStatusEnum::PENDING->value,
                'transaction_details' => [
                    'receiver_name' => 'John Doe',
                    'card_number'   => '1234567890123456',
                    'iban_number'   => 'IR123456789012345678901234',
                    'tracking_code' => 'TRK1234567890',
                ],
            ];

            $response = $this->postJson(route('api.v1.admin.refund.store', ['orderItem' => $orderItem->id]), $data);
            $response->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        })->with([
            [null, 110, 'deduction_percent'], // Deduction percent exceeds maximum
            [10000, 80, 'deduction_amount'], // Deduction percent is valid
            [null, -10, 'deduction_percent'], // Negative deduction percent
        ]);

    it('should not create a refund for an order item that is not refundable', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::REFUND_CREATE]);
        $order = Order::factory()->withCalculatedTotals(
            [
                [
                    'total'  => 50000,
                    'price'  => 50000,
                    'status' => OrderItemStatusEnum::COMPLETED->value,
                ],
            ]
        )->create();
        $order->payments()->create([
            'customer_id' => $order->customer_id,
            'method'      => PaymentMethodEnum::BANK_TRANSFER,
            'amount'      => 50000,
            'status'      => PaymentStatusEnum::COMPLETED,
        ]);

        $orderItem         = $order->items()->first();
        $orderItem->status = OrderItemStatusEnum::CANCELLED;
        $orderItem->save();

        $data = [
            'deduction_percent'   => 20,
            'status'              => RefundStatusEnum::PENDING->value,
            'transaction_details' => [
                'receiver_name' => 'John Doe',
                'card_number'   => '1234567890123456',
                'iban_number'   => 'IR123456789012345678901234',
                'tracking_code' => 'TRK1234567890',
            ],
        ];

        $response = $this->postJson(route('api.v1.admin.refund.store', ['orderItem' => $orderItem->id]), $data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['order_item_id' => __('messages.order.refund.not_allowed')]);

        $orderItem->status = OrderItemStatusEnum::REFUNDED;
        $orderItem->save();
        $response = $this->postJson(route('api.v1.admin.refund.store', ['orderItem' => $orderItem->id]), $data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['order_item_id' => __('messages.order.refund.already_refunded')]);

        $order->payments()->delete();
        $response = $this->postJson(route('api.v1.admin.refund.store', ['orderItem' => $orderItem->id]), $data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['order_item_id' => __('messages.order.refund.no_completed_payments')]);
    });

    it('should not create a refund for an order item that does not exist', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::REFUND_CREATE]);
        $data = [
            'deduction_percent'   => 20,
            'status'              => RefundStatusEnum::PENDING->value,
            'transaction_details' => [
                'receiver_name' => 'John Doe',
                'card_number'   => '1234567890123456',
                'iban_number'   => 'IR123456789012345678901234',
                'tracking_code' => 'TRK1234567890',
            ],
        ];

        $response = $this->postJson(route('api.v1.admin.refund.store', ['orderItem' => 999]), $data);
        $response->assertNotFound();
    });

    it('should update only the data of a pending refund', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::REFUND_UPDATE]);
        $order = Order::factory()->withCalculatedTotals(
            [
                [
                    'total'  => 50000,
                    'price'  => 50000,
                    'status' => OrderItemStatusEnum::COMPLETED->value,
                ],
            ]
        )->create();
        $order->payments()->create([
            'customer_id' => $order->customer_id,
            'method'      => PaymentMethodEnum::BANK_TRANSFER,
            'amount'      => 50000,
            'status'      => PaymentStatusEnum::COMPLETED,
        ]);

        $orderItem = $order->items()->first();
        $refund    = Refund::factory()->create([
            'order_item_id' => $orderItem->id,
            'status'        => RefundStatusEnum::PENDING,
        ]);

        $data = [
            'deduction_amount'    => 5000,
            'status'              => RefundStatusEnum::PENDING->value, // Status should not change
            'transaction_details' => [
                'receiver_name' => 'Jane Doe',
                'card_number'   => '6543210987654321',
                'iban_number'   => 'IR098765432109876543210987',
                'tracking_code' => 'TRK0987654321',
            ],
            'admin_notes' => 'Updated refund details',
        ];

        $response = $this->putJson(route('api.v1.admin.refund.update',
            ['orderItem' => $orderItem, 'refund' => $refund->id]), $data);
        $response->assertOk()
            ->assertJson(function (Illuminate\Testing\Fluent\AssertableJson $json): void {
                $json
                    ->where('data.deduction_amount', 5000)
                    ->where('data.transaction_details.receiver_name', 'Jane Doe')
                    ->where('data.transaction_details.card_number', '6543210987654321')
                    ->where('data.transaction_details.iban_number', 'IR098765432109876543210987')
                    ->where('data.admin_notes', 'Updated refund details')
                    ->etc();
            });

        assertDatabaseHas('refunds', [
            'id'                                 => $refund->id,
            'deduction_amount'                   => 5000,
            'transaction_details->receiver_name' => 'Jane Doe',
            'transaction_details->card_number'   => '6543210987654321',
            'transaction_details->iban_number'   => 'IR098765432109876543210987',
            'admin_notes'                        => 'Updated refund details',
        ]);
        $refund->refresh();
        expect($refund->status)->toBe(RefundStatusEnum::PENDING);

    });

    it('should not update a refund that is not pending', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::REFUND_UPDATE]);
        $order = Order::factory()->withCalculatedTotals(
            [
                [
                    'total'  => 50000,
                    'price'  => 50000,
                    'status' => OrderItemStatusEnum::COMPLETED->value,
                ],
            ]
        )->create();
        $order->payments()->create([
            'customer_id' => $order->customer_id,
            'method'      => PaymentMethodEnum::BANK_TRANSFER,
            'amount'      => 50000,
            'status'      => PaymentStatusEnum::COMPLETED,
        ]);

        $orderItem = $order->items()->first();
        $refund    = Refund::factory()->create([
            'order_item_id' => $orderItem->id,
            'status'        => RefundStatusEnum::COMPLETED, // Not pending
        ]);

        $data = [
            'deduction_amount'    => 5000,
            'status'              => RefundStatusEnum::PENDING->value, // Trying to change status
            'transaction_details' => [
                'receiver_name' => 'Jane Doe',
                'card_number'   => '6543210987654321',
                'iban_number'   => 'IR098765432109876543210987',
                'tracking_code' => 'TRK0987654321',
            ],
            'admin_notes' => 'Updated refund details',
        ];

        $response = $this->putJson(route('api.v1.admin.refund.update',
            ['orderItem' => $orderItem, 'refund' => $refund->id]), $data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['refund' => __('messages.order.refund.only_pending_refunds_can_be_edited')]);
    });

    it('show detail of a refund', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::REFUND_VIEW]);
        $order = Order::factory()->withCalculatedTotals(
            [
                [
                    'total'  => 50000,
                    'price'  => 50000,
                    'status' => OrderItemStatusEnum::COMPLETED->value,
                ],
            ]
        )->create();
        $order->payments()->create([
            'customer_id' => $order->customer_id,
            'method'      => PaymentMethodEnum::BANK_TRANSFER,
            'amount'      => 50000,
            'status'      => PaymentStatusEnum::COMPLETED,
        ]);

        $orderItem = $order->items()->first();
        $refund    = Refund::factory()->create([
            'order_item_id'       => $orderItem->id,
            'status'              => RefundStatusEnum::PENDING,
            'transaction_details' => [
                'receiver_name' => 'John Doe',
                'card_number'   => '1234567890123456',
                'iban_number'   => 'IR123456789012345678901234',
                'tracking_code' => 'TRK1234567890',
            ],
        ]);

        $response = $this->getJson(route('api.v1.admin.refund.show',
            ['orderItem' => $orderItem, 'refund' => $refund->id]));
        $response->assertOk()
            ->assertJson(function (Illuminate\Testing\Fluent\AssertableJson $json) use ($refund): void {
                $json
                    ->where('data.id', $refund->id)
                    ->where('data.deduction_amount', $refund->deduction_amount)
                    ->where('data.status', [
                        'value' => RefundStatusEnum::PENDING->value,
                        'label' => RefundStatusEnum::PENDING->translate(),
                    ])
                    ->where('data.transaction_details.receiver_name', $refund->transaction_details['receiver_name'])
                    ->where('data.transaction_details.card_number', $refund->transaction_details['card_number'])
                    ->where('data.transaction_details.iban_number', $refund->transaction_details['iban_number'])
                    ->etc();
            });
    });

    it('should delete a pending refund', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::REFUND_DELETE]);
        $order = Order::factory()->withCalculatedTotals(
            [
                [
                    'total'  => 50000,
                    'price'  => 50000,
                    'status' => OrderItemStatusEnum::COMPLETED->value,
                ],
            ]
        )->create();
        $order->payments()->create([
            'customer_id' => $order->customer_id,
            'method'      => PaymentMethodEnum::BANK_TRANSFER,
            'amount'      => 50000,
            'status'      => PaymentStatusEnum::COMPLETED,
        ]);

        $orderItem = $order->items()->first();
        $refund    = Refund::factory()->create([
            'order_item_id' => $orderItem->id,
            'status'        => RefundStatusEnum::PENDING,
        ]);

        $response = $this->deleteJson(route('api.v1.admin.refund.destroy',
            ['orderItem' => $orderItem, 'refund' => $refund->id]));
        $response->assertNoContent();

        \Pest\Laravel\assertDatabaseMissing('refunds', ['id' => $refund->id]);
    });

    it('should not delete a refund that is not pending', function (RefundStatusEnum $status): void {
        $this->authorized_user([App\Enums\PermissionEnum::REFUND_DELETE]);
        $order = Order::factory()->withCalculatedTotals(
            [
                [
                    'total'  => 50000,
                    'price'  => 50000,
                    'status' => OrderItemStatusEnum::COMPLETED->value,
                ],
            ]
        )->create();
        $order->payments()->create([
            'customer_id' => $order->customer_id,
            'method'      => PaymentMethodEnum::BANK_TRANSFER,
            'amount'      => 50000,
            'status'      => PaymentStatusEnum::COMPLETED,
        ]);

        $orderItem = $order->items()->first();
        $refund    = Refund::factory()->create([
            'order_item_id' => $orderItem->id,
            'status'        => $status, // Not pending
        ]);

        $response = $this->deleteJson(route('api.v1.admin.refund.destroy',
            ['orderItem' => $orderItem, 'refund' => $refund->id]));
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => __('messages.order.refund.only_pending_refunds_can_be_deleted')]);
    })->with([
        [RefundStatusEnum::COMPLETED],
        [RefundStatusEnum::FAILED],
        [RefundStatusEnum::CANCELLED],
    ]);

    it('should not view list of refunds without permission', function (): void {
        $this->unauthorized_user();
        $orderItem = App\Models\OrderItem::factory()->create();
        Refund::factory()->create([
            'order_item_id' => $orderItem->id,
            'status'        => RefundStatusEnum::PENDING,
        ]);

        $response = $this->getJson(route('api.v1.admin.refund.index', ['orderItem' => $orderItem->id]));
        $response->assertForbidden();
    });

    it('should not create a refund without permission', function (): void {
        $this->unauthorized_user();
        $orderItem = App\Models\OrderItem::factory()->create();
        Refund::factory()->create([
            'order_item_id' => $orderItem->id,
            'status'        => RefundStatusEnum::PENDING,
        ]);
        $data = [
            'deduction_percent'   => 20,
            'status'              => RefundStatusEnum::COMPLETED->value,
            'transaction_details' => [
                'receiver_name' => 'John Doe',
                'card_number'   => '1234567890123456',
                'iban_number'   => 'IR123456789012345678901234',
                'tracking_code' => 'TRK1234567890',
            ],
        ];

        $response = $this->postJson(route('api.v1.admin.refund.store', ['orderItem' => $orderItem->id]), $data);
        $response->assertForbidden();
    });

    it('should not update a refund without permission', function (): void {
        $this->unauthorized_user();
        $orderItem = App\Models\OrderItem::factory()->create();
        $refund    = Refund::factory()->create([
            'order_item_id' => $orderItem->id,
            'status'        => RefundStatusEnum::PENDING,
        ]);

        $data = [
            'deduction_amount'    => 5000,
            'status'              => RefundStatusEnum::PENDING->value, // Status should not change
            'transaction_details' => [
                'receiver_name' => 'Jane Doe',
                'card_number'   => '6543210987654321',
                'iban_number'   => 'IR098765432109876543210987',
                'tracking_code' => 'TRK0987654321',
            ],
            'admin_notes' => 'Updated refund details',
        ];

        $response = $this->putJson(route('api.v1.admin.refund.update',
            ['orderItem' => $orderItem, 'refund' => $refund->id]), $data);
        $response->assertForbidden();
    });

    it('should not delete a refund without permission', function (): void {
        $this->unauthorized_user();
        $refund = Refund::factory()->create([
            'status' => RefundStatusEnum::PENDING,
        ]);

        $response = $this->deleteJson(route('api.v1.admin.refund.destroy',
            ['orderItem' => $refund->order_item_id, 'refund' => $refund->id]));
        $response->assertForbidden();
    });

});
