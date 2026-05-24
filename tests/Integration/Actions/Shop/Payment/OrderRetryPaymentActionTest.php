<?php

declare(strict_types=1);

use App\Actions\Shop\RetryOrderPaymentAction;
use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

uses(Tests\Support\Traits\AuthTestTrait::class);

describe('Retry Order Payment', function (): void {
    it('prevent payment if amount to pay is 0', function (): void {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'customer_id'            => $user->id,
            'status'                 => OrderStatusEnum::PENDING,
            'grand_total'            => 0,
            'full_value_grand_total' => 500000,
        ]);

        $this->customer($user);
        $action = app()->make(RetryOrderPaymentAction::class);

        expect(fn (): PaymentProcessResultData => $action->handle($order, PaymentMethodEnum::WALLET, 0))
            ->toThrow(ValidationException::class, __('validation.custom.checkout.no_outstanding_balance'));
    });

    it('prevent payment if amount to pay is more than balance due', function (): void {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'customer_id'            => $user->id,
            'status'                 => OrderStatusEnum::PENDING,
            'grand_total'            => 100000,
            'full_value_grand_total' => 100000,
        ]);

        $this->customer($user);
        $user->wallet->update(['balance' => 500000]);
        $action = app()->make(RetryOrderPaymentAction::class);

        expect(fn (): PaymentProcessResultData => $action->handle($order, PaymentMethodEnum::WALLET, 500000))
            ->toThrow(ValidationException::class, __('validation.custom.checkout.payment_exceeds_balance_due', ['balance_due' => $order->balance_due]));
    });
});
