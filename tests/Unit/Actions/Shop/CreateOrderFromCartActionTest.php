<?php

use App\Actions\Shop\CreateOrderFromCartAction;
use App\Data\Shop\Cart\CheckoutData;
use Illuminate\Validation\ValidationException;
use Tests\AuthTestTrait;

uses(AuthTestTrait::class);
it('throws validation error when payment method is not provided', function () {
    $this->customer();
    $pdo = \App\Models\ProductDeliveryOption::factory()->create();
    \App\Models\Cart::factory()
        ->create([
            'user_id' => $this->user->id,
        ]);
    \App\Models\CartItem::factory()
        ->create([
            'cart_id'                     => \App\Models\Cart::first()->id,
            'product_delivery_option_id'  => $pdo->id,
            'quantity'                    => 1,
        ]);
    $action = app(CreateOrderFromCartAction::class);
    expect(fn() => $action->handle(new CheckoutData(),$this->user))
        ->toThrow(ValidationException::class,__('validation.custom.checkout.payment_method_required'));
});
