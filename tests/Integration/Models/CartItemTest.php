<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductDeliveryOption;

test('to array', function (): void {
    $cartItem = CartItem::factory()->create()->fresh();

    $array = $cartItem->toArray();
    expect($array)->toHaveKeys([
        'id',
        'cart_id',
        'product_delivery_option_id',
        'quantity',
        'created_at',
        'updated_at',
    ]);
});

test('relationships', function (): void {
    $cartItem = CartItem::factory()->create()->fresh();

    // Test cart relationship
    $cart = $cartItem->cart;
    expect($cart)->toBeInstanceOf(Cart::class);

    // Test product delivery option relationship
    $productDeliveryOption = $cartItem->productDeliveryOption;
    expect($productDeliveryOption)->toBeInstanceOf(ProductDeliveryOption::class);

});
