<?php

use App\Actions\Payment\CreatePaymentAction;
use App\Data\Admin\Payment\PaymentCreateData;
use App\Enums\OrderItemPaymentTypeEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

// Assuming you have created this enum in app/Enums/PaymentMethodEnum.php
// enum PaymentMethodEnum: string { case BANK_TRANSFER = 'bank_transfer'; case ONLINE_GATEWAY = 'online_gateway'; }

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

// Group all tests for this action
describe('CreatePaymentAction', function () {

    // Setup common users for all tests
    beforeEach(function () {
        $this->adminUser = \App\Models\Staff::factory()->create();
        $this->customer = User::factory()->create();
    });

    // Test the main success case for pre-payments
    it('creates a pending payment record based on pre-payment intent', function () {
        // ARRANGE: Create an order with items that have a pre-payment intent
        $order = Order::factory()->create(['grand_total' => 200000, 'customer_id' => $this->customer->id]);
        OrderItem::factory()->create([
            'order_id'          => $order->id,
            'price'             => 100000,
            'prepayment_amount' => 25000,
            'payment_type'      => OrderItemPaymentTypeEnum::PRE_PAYMENT->value,
            'qty_ordered'       => 2,
        ]);
        $paymentData = new PaymentCreateData(
            method: PaymentMethodEnum::ONLINE_GATEWAY->value,
            status: PaymentStatusEnum::PENDING->value,
            data: ['transaction_id' => 'txn_12345'],
            admin_notes: 'Initial online payment'
        );

        // ACT: Execute the action
        $action = new CreatePaymentAction();
        $action->handle($order, $paymentData, $this->adminUser);

        // ASSERT: Check that the correct payment record was created
        $this->assertDatabaseHas('payments', [
            'order_id'    => $order->id,
            'staff_id'    => $this->adminUser->id,
            'customer_id' => $this->customer->id,
            'amount'      => 50000, // Correctly calculated: 25,000 * 2
            'method'      => PaymentMethodEnum::ONLINE_GATEWAY->value,
            'status'      => 'pending',
            'admin_notes' => 'Initial online payment',
        ]);
        $this->assertDatabaseCount('payments', 1);
    });

    // Test the main success case for full payments
    it('creates a pending payment record based on full-payment intent', function () {
        // ARRANGE: An order with an item requiring full payment, with discounts and taxes
        $order = Order::factory()->create(['grand_total' => 95000, 'customer_id' => $this->customer->id]);
        OrderItem::factory()->create([
            'order_id'        => $order->id,
            'price'           => 100000,
            'discount_amount' => 10000,
            'tax_amount'      => 5000,
            'payment_type'    => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
            'qty_ordered'     => 1,
        ]);
        $paymentData = new PaymentCreateData
        (
            method: PaymentMethodEnum::ONLINE_GATEWAY->value,
            status: PaymentStatusEnum::PENDING->value,
            data: ['transaction_id' => 'txn_12345'],
            admin_notes: null
        );

        // ACT: Execute the action
        (new CreatePaymentAction())->handle($order, $paymentData, $this->adminUser);

        // ASSERT: Check that the amount is the item's total after discount/tax
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'amount'   => 95000, // 100,000 - 10,000 + 5,000
            'status'   => 'pending',
        ]);
    });

    // Test the business rule that prevents duplicate pending payments
    it('throws an exception if a pending payment already exists for the order', function () {
        // ARRANGE: Create an order that already has a pending payment
        $order = Order::factory()->create();
        Payment::factory()->create([
            'order_id' => $order->id,
            'status'   => 'pending',
        ]);
        $paymentData = new PaymentCreateData(
            method: PaymentMethodEnum::ONLINE_GATEWAY->value,
            status: PaymentStatusEnum::PENDING->value,
            data: ['transaction_id' => 'txn_12345'],
            admin_notes: null);

        // ACT & ASSERT: Expect a ValidationException when trying to create another one
        expect(fn() => (new CreatePaymentAction())->handle($order, $paymentData, $this->adminUser))
            ->toThrow(ValidationException::class);
    });

    // Test the business rule that payment amount is capped by the current balance_due
    it('caps the payment amount to the outstanding balance due', function () {
        // ARRANGE: Create an order that should have a 100k payment, but has already been partially paid
        $order = Order::factory()->create(['grand_total' => 100000]);
        // The items intent requires a 100k payment
        OrderItem::factory()->create([
            'order_id'     => $order->id,
            'price'        => 100000, 'discount_amount' => 0, 'tax_amount' => 0,
            'payment_type' => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
        ]);
        // Manually create a COMPLETED payment of 80k. The balance_due is now 20k.
        Payment::factory()->create(['order_id' => $order->id, 'amount' => 80000, 'status' => 'completed']);

        $paymentData = new PaymentCreateData(
            method: PaymentMethodEnum::ONLINE_GATEWAY->value,
            status: PaymentStatusEnum::PENDING->value,
            data: ['transaction_id' => 'txn_12345'],
            admin_notes: null);

        // ACT: Execute the action
        (new CreatePaymentAction())->handle($order, $paymentData, $this->adminUser);

        // ASSERT: Check that the NEW payment is for 20k (the balance), not 100k.
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'amount'   => 20000,
            'status'   => 'pending',
        ]);
        $this->assertDatabaseCount('payments', 2); // The original 80k + the new 20k
    });

    // Test the edge case where the calculated amount is zero
    it('does not create a payment record if the calculated amount is zero', function () {
        // ARRANGE: Create an order where the item has a 100% discount, making the required payment 0.
        $order = Order::factory()->create(['grand_total' => 0]);
        OrderItem::factory()->create([
            'order_id'     => $order->id,
            'price'        => 100000, 'discount_amount' => 100000, 'tax_amount' => 0,
            'payment_type' => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
        ]);
        $paymentData = new PaymentCreateData(
            method: PaymentMethodEnum::ONLINE_GATEWAY->value,
            status: PaymentStatusEnum::PENDING->value,
            data: ['transaction_id' => 'txn_12345'],
            admin_notes: null);

        // ACT: Execute the action
        (new CreatePaymentAction())->handle($order, $paymentData, $this->adminUser);

        // ASSERT: No payment record should have been created.
        $this->assertDatabaseCount('payments', 0);
    });
});
