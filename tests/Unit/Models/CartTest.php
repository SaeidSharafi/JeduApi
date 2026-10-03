<?php

declare(strict_types=1);
test('to array', function (): void {
    $cart  = App\Models\Cart::factory()->create()->fresh();
    $array = $cart->toArray();
    expect($array)->toHaveKeys([
        'id',
        'user_id',
        'guest_token',
        'applied_coupon_code',
        'created_at',
        'updated_at',
    ]);
});

test('relationships', function (): void {
    $user = App\Models\User::factory()->create()->fresh();
    $cart = App\Models\Cart::factory()->create(
        [
            'user_id' => $user->id,
        ]
    )->fresh();

    expect($cart->user)
        ->toBeInstanceOf(App\Models\User::class)
        ->and($cart->user->id)->toEqual($user->id);

    // Test items relationship
    $items = $cart->items;
    expect($items)->toBeInstanceOf(Illuminate\Database\Eloquent\Collection::class);
});
