<?php

declare(strict_types=1);

use App\Actions\Admin\Payment\GetNextPaymentDetailsAction;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Payment\NextPaymentTypeEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Models\Order;

describe('GetNextPaymentDetailsAction', function () {

    // Test for an order that is already fully paid
    it('throws an exception for an already paid order', function () {
        $order = Order::factory()->create(['grand_total' => 10000]);
        $order->payments()->create([
            'customer_id' => $order->customer_id,
            'method'      => PaymentMethodEnum::ONLINE_GATEWAY->value,
            'amount'      => 10000,
            'status'      => 'completed'
        ]);

        $action = new GetNextPaymentDetailsAction();
        expect(fn() => $action->handle($order->fresh()))
            ->toThrow(\Exception::class, __('messages.order.already_fully_paid', ['order_id' => $order->increment_id]));
    });

    // Test for a free order
    it('returns "none" details for a free order', function () {
        $order = Order::factory()->create(['grand_total' => 0]);

        $details = (new GetNextPaymentDetailsAction())->handle($order);

        expect($details->amount_due)->toBe(0);
        expect($details->payment_type->value)->toBe(NextPaymentTypeEnum::NONE->value);
        expect($details->summary_description)->toContain(__('messages.order.no_payment_required'));
    });

    // Test for an initial payment that is also a full payment
    it('returns correct details for an initial, full payment', function () {
        $order = Order::factory()->create(['grand_total' => 15000]);
        $prodcut = \App\Models\Product::factory()->create(['status' => \App\Enums\PublicationStatusEnum::PUBLISHED]);
        $prodcutDeliveryOption = \App\Models\ProductDeliveryOption::factory()->create([
            'product_id' => $prodcut->id,
            'price'      => 15000,
            'status'     => \App\Enums\PublicationStatusEnum::PUBLISHED
        ]);
        $orderItem = \App\Models\OrderItem::factory()->create([
            'order_id'                   => $order->id,
            'product_delivery_option_id' => $prodcutDeliveryOption->id,
            'qty_ordered'                => 1,
            'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
            'total'                      => 15000,
            'name'                       => 'Full Course'
        ]);

        $details = (new GetNextPaymentDetailsAction())->handle($order);

        expect($details->amount_due)->toBe(15000);
        expect($details->payment_type->value)->toBe('initial_payment');
        expect($details->summary_description)->toContain('full and final payment');
        expect($details->line_item_details[0]['items'][0])->toBe('Full Course');
    });

    // Test for an initial payment that is only a pre-payment
    it('returns correct details for an initial pre-payment', function () {
        $order = Order::factory()->create(['grand_total' => 100000]);
        $prodcut = \App\Models\Product::factory()->create(['status' => \App\Enums\PublicationStatusEnum::PUBLISHED]);
        $prodcutDeliveryOption = \App\Models\ProductDeliveryOption::factory()->create([
            'product_id' => $prodcut->id,
            'price'      => 15000,
            'status'     => \App\Enums\PublicationStatusEnum::PUBLISHED
        ]);
        $orderItem = \App\Models\OrderItem::factory()->create([
            'order_id'                   => $order->id,
            'product_delivery_option_id' => $prodcutDeliveryOption->id,
            'qty_ordered'                => 1,
            'payment_type'               => OrderItemPaymentTypeEnum::PRE_PAYMENT->value,
            'prepayment_amount'          => 20000,
            'total'                      => 100000,
            'name'                       => 'Workshop'
        ]);

        $details = (new GetNextPaymentDetailsAction())->handle($order);

        expect($details->amount_due)->toBe(20000);
        expect($details->payment_type->value)->toBe(NextPaymentTypeEnum::INITIAL_PAYMENT->value);
        expect($details->summary_description)->toContain(__('messages.order.initial_payment_partial'));
        expect($details->line_item_details[0]['items'][0])->toBe('Workshop');
    });
    it('returns correct details for an initial pre-payment wit mixed payments', function () {
        $order = Order::factory()->create(['grand_total' => 170000]);
        $prodcut = \App\Models\Product::factory()->create(['status' => \App\Enums\PublicationStatusEnum::PUBLISHED]);
        $prodcutDeliveryOption = \App\Models\ProductDeliveryOption::factory()->create([
            'product_id' => $prodcut->id,
            'price'      => 150000,
            'status'     => \App\Enums\PublicationStatusEnum::PUBLISHED
        ]);
        $prodcutDeliveryOption2 = \App\Models\ProductDeliveryOption::factory()->create([
            'product_id' => $prodcut->id,
            'price'      => 100000,
            'status'     => \App\Enums\PublicationStatusEnum::PUBLISHED,
            'is_prepayment_available' => true,
            'prepayment_amount' => 20000
        ]);
        $orderItem = \App\Models\OrderItem::factory()->create([
            'order_id'                   => $order->id,
            'product_delivery_option_id' => $prodcutDeliveryOption->id,
            'qty_ordered'                => 1,
            'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
            'prepayment_amount'          => 0,
            'total'                      => 150000,
            'name'                       => 'Workshop'
        ]);
        $orderItem = \App\Models\OrderItem::factory()->create([
            'order_id'                   => $order->id,
            'product_delivery_option_id' => $prodcutDeliveryOption->id,
            'qty_ordered'                => 1,
            'payment_type'               => OrderItemPaymentTypeEnum::PRE_PAYMENT->value,
            'prepayment_amount'          => 20000,
            'total'                      => 100000,
            'name'                       => 'Workshop'
        ]);
        $details = (new GetNextPaymentDetailsAction())->handle($order);

        expect($details->amount_due)->toBe(170000);
        expect($details->payment_type->value)->toBe(NextPaymentTypeEnum::INITIAL_PAYMENT->value);
        expect($details->summary_description)->toContain(__('messages.order.initial_payment_mixed'));
        expect($details->line_item_details[0]['items'][0])->toBe('Workshop');
    });
    // Test for a final balance payment
    it('returns correct details for a final balance payment', function () {
        $order = Order::factory()->create(['grand_total' => 100000]);
        $prodcut = \App\Models\Product::factory()->create(['status' => \App\Enums\PublicationStatusEnum::PUBLISHED]);
        $prodcutDeliveryOption = \App\Models\ProductDeliveryOption::factory()->create([
            'product_id' => $prodcut->id,
            'price'      => 15000,
            'status'     => \App\Enums\PublicationStatusEnum::PUBLISHED
        ]);
        $orderItem = \App\Models\OrderItem::factory()->create([
            'order_id'                   => $order->id,
            'product_delivery_option_id' => $prodcutDeliveryOption->id,
            'qty_ordered'                => 1,
            'payment_type'               => OrderItemPaymentTypeEnum::PRE_PAYMENT->value,
            'total'                      => 100000,
            'name'                       => 'Workshop'
        ]);

        $order->payments()->create([
            'customer_id' => $order->customer_id,
            'method'      => PaymentMethodEnum::ONLINE_GATEWAY->value,
            'amount'      => 20000,
            'status'      => \App\Enums\Payment\PaymentStatusEnum::COMPLETED
        ]);

        $details = (new GetNextPaymentDetailsAction())->handle($order->fresh());

        expect($details->amount_due)->toBe(80000); // 100k - 20k
        expect($details->payment_type->value)->toBe(NextPaymentTypeEnum::FINAL_BALANCE->value);
        expect($details->summary_description)->toContain(__('messages.order.final_balance_payment_required'));
        expect($details->line_item_details[0]['items'][0])->toBe('Workshop');
    });
});
