<?php

declare(strict_types=1);

use App\Actions\Admin\Refund\UpdateRefundAction;
use App\Data\Admin\Refund\RefundCreateData;
use App\Data\Admin\Refund\RefundTransactionData;
use App\Enums\Order\RefundStatusEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;

describe('UpdateRefundAction', function (): void {
    it('updates a pending refund with deduction_amount', function (): void {
        $order = Order::factory()
            ->withCalculatedTotals([
                ['price' => 2000, 'total' => 2000],
            ])->state([
                'customer_id' => User::factory()->create()->id,
            ])
            ->create();
        $orderItem = $order->items()->first();
        $refund    = App\Models\Refund::factory()->create([
            'order_item_id'    => $orderItem->id,
            'status'           => RefundStatusEnum::PENDING,
            'amount'           => 0,
            'deduction_amount' => 0,
        ]);
        $data = new RefundCreateData(
            deduction_amount: 500,
            deduction_percent: null,
            transaction_details: new RefundTransactionData(
                receiver_name: 'John Doe',
                card_number: '1234567812345678',
                iban_number: 'IR123456789012345678901234',
                tracking_code: 'TRACK123',
            ),
            status: RefundStatusEnum::PENDING->value,
            admin_notes: 'Test note',
        );
        $action  = new UpdateRefundAction();
        $updated = $action->handle($refund, $data);
        expect($updated->amount)->toBe(1500)
            ->and($updated->deduction_amount)->toBe(500)
            ->and($updated->transaction_details['receiver_name'])->toBe('John Doe')
            ->and($updated->admin_notes)->toBe('Test note');
    });

    it('updates a pending refund with deduction_percent', function (): void {

        $order = Order::factory()
            ->withCalculatedTotals([
                ['price' => 2000, 'total' => 2000],
            ])->state([
                'customer_id' => User::factory()->create()->id,
            ])
            ->create()
            ->fresh();
        $orderItem = $order->items()->first();

        $refund = App\Models\Refund::factory()->create([
            'order_item_id'    => $orderItem->id,
            'status'           => RefundStatusEnum::PENDING,
            'amount'           => 0,
            'deduction_amount' => 0,
        ]);

        $data = new RefundCreateData(
            deduction_amount: null,
            deduction_percent: 25,
            transaction_details: new RefundTransactionData(
                receiver_name: 'Jane Doe',
                card_number: '8765432187654321',
                iban_number: 'IR987654321098765432109876',
                tracking_code: 'TRACK456',
            ),
            status: RefundStatusEnum::PENDING->value,
            admin_notes: 'Percent note',
        );
        $action  = new UpdateRefundAction();
        $updated = $action->handle($refund, $data);
        expect($updated->amount)->toBe(1500)
            ->and($updated->deduction_amount)->toBe(500)
            ->and($updated->transaction_details['receiver_name'])->toBe('Jane Doe')
            ->and($updated->admin_notes)->toBe('Percent note');
    });

    it('throws if refund is not pending', function (): void {
        $orderItem = OrderItem::factory()->create();
        $refund    = App\Models\Refund::factory()->create([
            'order_item_id' => $orderItem->id,
            'status'        => RefundStatusEnum::COMPLETED,
        ]);
        $data = new RefundCreateData(
            deduction_amount: 100,
            deduction_percent: null,
            transaction_details: new RefundTransactionData(
                receiver_name: 'John Doe',
                card_number: '1234567812345678',
                iban_number: 'IR123456789012345678901234',
                tracking_code: 'TRACK123',
            ),
            status: RefundStatusEnum::COMPLETED->value,
            admin_notes: 'Should fail',
        );
        $action = new UpdateRefundAction();
        expect(fn (): \App\Models\Refund => $action->handle($refund, $data))
            ->toThrow(Illuminate\Validation\ValidationException::class);
    });

    it('refund amount never goes below zero', function (): void {
        $order = Order::factory()->create([
            'full_value_grand_total' => 10000,
            'grand_total'            => 10000,
            'subtotal'               => 10000,
            'discount_amount'        => 0,
            'tax_amount'             => 0,
        ]);
        $orderItem = OrderItem::factory()->create([
            'order_id'        => $order->id,
            'price'           => 1000,
            'discount_amount' => 0,
            'tax_amount'      => 0,
            'qty_ordered'     => 1,
            'total'           => 1000,
        ]);
        $refund = App\Models\Refund::factory()->create([
            'order_item_id'    => $orderItem->id,
            'status'           => RefundStatusEnum::PENDING,
            'amount'           => 0,
            'deduction_amount' => 0,
        ]);
        $data = new RefundCreateData(
            deduction_amount: 2000,
            deduction_percent: null,
            transaction_details: new RefundTransactionData(
                receiver_name: 'Zero Doe',
                card_number: '0000000000000000',
                iban_number: 'IR000000000000000000000000',
                tracking_code: 'ZERO',
            ),
            status: RefundStatusEnum::PENDING->value,
            admin_notes: 'Zero test',
        );
        $action  = new UpdateRefundAction();
        $updated = $action->handle($refund, $data);
        expect($updated->amount)->toBe(0);
    });

    it('uses fallback deduction amount as zero if both are null', function (): void {
        $order = Order::factory()->create([
            'full_value_grand_total' => 10000,
            'grand_total'            => 10000,
            'subtotal'               => 10000,
            'discount_amount'        => 0,
            'tax_amount'             => 0,
        ]);
        $orderItem = OrderItem::factory()->create([
            'order_id'        => $order->id,
            'price'           => 1000,
            'discount_amount' => 0,
            'tax_amount'      => 0,
            'qty_ordered'     => 1,
            'total'           => 1000,
        ]);
        $refund = App\Models\Refund::factory()->create([
            'order_item_id'    => $orderItem->id,
            'status'           => RefundStatusEnum::PENDING,
            'amount'           => 0,
            'deduction_amount' => 0,
        ]);
        $data = new RefundCreateData(
            deduction_amount: null,
            deduction_percent: null,
            transaction_details: new RefundTransactionData(
                receiver_name: 'Fallback Doe',
                card_number: '1111111111111111',
                iban_number: 'IR111111111111111111111111',
                tracking_code: 'FALLBACK',
            ),
            status: RefundStatusEnum::PENDING->value,
            admin_notes: 'Fallback test',
        );
        $action  = new UpdateRefundAction();
        $updated = $action->handle($refund, $data);
        expect($updated->deduction_amount)->toBe(0)
            ->and($updated->amount)->toBe(1000);
    });
});
