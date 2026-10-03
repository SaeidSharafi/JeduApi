<?php

declare(strict_types=1);

use App\Actions\Admin\Payment\GetNextPaymentDetailsAction;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Payment\NextPaymentTypeEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Models\Order;

describe('GetNextPaymentDetailsAction', function (): void {

    // Test for an order that is already fully paid
    it('throws an exception for an already paid order', function (): void {
        $order = Order::factory()->create(['grand_total' => 10000]);
        $order->payments()->create([
            'customer_id' => $order->customer_id,
            'method'      => PaymentMethodEnum::MELLAT_GATEWAY->value,
            'amount'      => 10000,
            'status'      => 'completed',
        ]);

        $action = new GetNextPaymentDetailsAction();
        expect(fn (): App\Data\Admin\Payment\NextPaymentDetailsData => $action->handle($order->fresh()))
            ->toThrow(Exception::class, __('messages.order.already_fully_paid', ['order_id' => $order->increment_id]));
    });

    // Test for a free order
    it('returns "none" details for a free order', function (): void {
        $order = Order::factory()->create(['grand_total' => 0]);

        $details = (new GetNextPaymentDetailsAction())->handle($order);

        expect($details->amount_due)->toBe(0)
            ->and($details->payment_type->value)->toBe(NextPaymentTypeEnum::NONE->value)
            ->and($details->summary_description)->toContain(__('messages.order.no_payment_required'));
    });

    // Test for an initial payment that is also a full payment
    it('returns correct details for an initial, full payment', function (): void {
        $prodcut               = App\Models\Product::factory()->create(['status' => App\Enums\Content\PublicationStatusEnum::PUBLISHED]);
        $prodcutDeliveryOption = App\Models\ProductDeliveryOption::factory()->create([
            'product_id' => $prodcut->id,
            'price'      => 15000,
            'status'     => App\Enums\Content\PublicationStatusEnum::PUBLISHED,
        ]);
        $items = [
            [
                'product_delivery_option_id' => $prodcutDeliveryOption->id,
                'qty_ordered'                => 1,
                'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                'total'                      => $prodcutDeliveryOption->price,
                'price'                      => $prodcutDeliveryOption->price,
                'name'                       => 'Full Course',
            ],
        ];
        $order = Order::factory()
            ->withCalculatedTotals($items)
            ->create();

        $details = (new GetNextPaymentDetailsAction())->handle($order);

        expect($details->amount_due)->toBe(15000)
            ->and($details->payment_type->value)->toBe('initial_payment')
            ->and($details->summary_description)->toContain('full and final payment')
            ->and($details->line_item_details[0]['items'][0])->toBe('Full Course');
    });

    // Test for an initial payment that is only a pre-payment
    it('returns correct details for an initial pre-payment', function (): void {
        $prodcut               = App\Models\Product::factory()->create(['status' => App\Enums\Content\PublicationStatusEnum::PUBLISHED]);
        $prodcutDeliveryOption = App\Models\ProductDeliveryOption::factory()->create([
            'product_id'              => $prodcut->id,
            'price'                   => 100000,
            'status'                  => App\Enums\Content\PublicationStatusEnum::PUBLISHED,
            'is_prepayment_available' => true,
            'prepayment_amount'       => 20000,
        ]);
        $items = [
            [
                'product_delivery_option_id' => $prodcutDeliveryOption->id,
                'qty_ordered'                => 1,
                'payment_type'               => OrderItemPaymentTypeEnum::PRE_PAYMENT->value,
                'total'                      => $prodcutDeliveryOption->prepayment_amount,
                'price'                      => $prodcutDeliveryOption->price,
                'name'                       => 'Workshop',
            ],
        ];
        $order = Order::factory()
            ->withCalculatedTotals($items)
            ->create()
            ->fresh();

        $details = (new GetNextPaymentDetailsAction())->handle($order);

        expect($details->amount_due)->toBe(20000)
            ->and($details->payment_type->value)->toBe(NextPaymentTypeEnum::INITIAL_PAYMENT->value)
            ->and($details->summary_description)->toContain(__('messages.order.initial_payment_partial'))
            ->and($details->line_item_details[0]['items'][0])->toBe('Workshop');
    });
    it('returns correct details for an initial pre-payment wit mixed payments', function (): void {
        $prodcut               = App\Models\Product::factory()->create(['status' => App\Enums\Content\PublicationStatusEnum::PUBLISHED]);
        $prodcutDeliveryOption = App\Models\ProductDeliveryOption::factory()->create([
            'product_id' => $prodcut->id,
            'price'      => 150000,
            'status'     => App\Enums\Content\PublicationStatusEnum::PUBLISHED,
        ]);
        $prodcutDeliveryOption2 = App\Models\ProductDeliveryOption::factory()->create([
            'product_id'              => $prodcut->id,
            'price'                   => 100000,
            'status'                  => App\Enums\Content\PublicationStatusEnum::PUBLISHED,
            'is_prepayment_available' => true,
            'prepayment_amount'       => 20000,
        ]);

        $items = [
            [
                'product_delivery_option_id' => $prodcutDeliveryOption->id,
                'qty_ordered'                => 1,
                'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                'total'                      => $prodcutDeliveryOption->price,
                'price'                      => $prodcutDeliveryOption->price,
                'name'                       => 'Full Course',
            ],
            [
                'product_delivery_option_id' => $prodcutDeliveryOption2->id,
                'qty_ordered'                => 1,
                'payment_type'               => OrderItemPaymentTypeEnum::PRE_PAYMENT->value,
                'total'                      => $prodcutDeliveryOption2->prepayment_amount,
                'price'                      => $prodcutDeliveryOption2->price,
                'name'                       => 'Workshop',
            ],
        ];
        $order = Order::factory()
            ->withCalculatedTotals($items)
            ->create()
            ->fresh();

        $details = (new GetNextPaymentDetailsAction())->handle($order);

        expect($details->amount_due)->toBe(170000)
            ->and($details->payment_type->value)->toBe(NextPaymentTypeEnum::INITIAL_PAYMENT->value)
            ->and($details->summary_description)->toContain(__('messages.order.initial_payment_mixed'))
            ->and($details->line_item_details[0]['items'][0])->toBe('Full Course');
    });
    // Test for a final balance payment
    it('returns correct details for a final balance payment', function (): void {
        $order                 = Order::factory()->create(['grand_total' => 100000]);
        $prodcut               = App\Models\Product::factory()->create(['status' => App\Enums\Content\PublicationStatusEnum::PUBLISHED]);
        $prodcutDeliveryOption = App\Models\ProductDeliveryOption::factory()->create([
            'product_id'              => $prodcut->id,
            'price'                   => 100000,
            'status'                  => App\Enums\Content\PublicationStatusEnum::PUBLISHED,
            'is_prepayment_available' => true,
            'prepayment_amount'       => 20000,
        ]);
        $items = [
            [
                'product_delivery_option_id' => $prodcutDeliveryOption->id,
                'qty_ordered'                => 1,
                'payment_type'               => OrderItemPaymentTypeEnum::PRE_PAYMENT->value,
                'total'                      => $prodcutDeliveryOption->prepayment_amount,
                'price'                      => $prodcutDeliveryOption->price,
                'name'                       => 'Full Course',
            ],
        ];
        $order = Order::factory()
            ->withCalculatedTotals($items)
            ->create();

        $order->payments()->create([
            'customer_id' => $order->customer_id,
            'method'      => PaymentMethodEnum::MELLAT_GATEWAY->value,
            'amount'      => 20000,
            'status'      => App\Enums\Payment\PaymentStatusEnum::COMPLETED,
        ]);
        $order->refresh();

        $details = (new GetNextPaymentDetailsAction())->handle($order->fresh());

        expect($details->amount_due)->toBe(80000)
            ->and($details->payment_type->value)->toBe(NextPaymentTypeEnum::FINAL_BALANCE->value)
            ->and($details->summary_description)->toContain(__('messages.order.final_balance_payment_required'))
            ->and($details->line_item_details[0]['items'][0])->toBe('Full Course'); // 100k - 20k
    });
});
