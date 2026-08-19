<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductDeliveryOption;
use App\Models\User;

use function Pest\Laravel\postJson;

it('do nothing if X-Guest-Token is not UUID', function (): void {
    $user     = User::factory()->create(['password' => bcrypt('password')]);
    $userCart = Cart::factory()->create(['user_id' => $user->id]);

    $deliveryOption = ProductDeliveryOption::factory()->create();
    CartItem::factory()->create([
        'cart_id'                    => $userCart->id,
        'product_delivery_option_id' => $deliveryOption->id,
        'quantity'                   => 2,
    ]);

    // Act: Login without guest token
    $response = postJson(route('api.v1.auth.password-login'), [
        'identifier' => $user->email,
        'password'   => 'password',
    ], [
        'X-Guest-Token' => 'not-a-uuid',
    ]);

    // Assert: Login successful
    $response->assertOk();

    // Assert: User cart remains unchanged
    $userCart->refresh();
    expect($userCart->items)->toHaveCount(1);

    $this->assertDatabaseHas('cart_items', [
        'cart_id'                    => $userCart->id,
        'product_delivery_option_id' => $deliveryOption->id,
        'quantity'                   => 2,
    ]);
});
it('do nothing if there is no guest cart or cart is empty', function (): void {
    // Arrange: Create a user with an existing cart
    $user     = User::factory()->create(['password' => bcrypt('password')]);
    $userCart = Cart::factory()->create(['user_id' => $user->id]);

    $deliveryOption = ProductDeliveryOption::factory()->create();
    CartItem::factory()->create([
        'cart_id'                    => $userCart->id,
        'product_delivery_option_id' => $deliveryOption->id,
        'quantity'                   => 2,
    ]);

    // Act: Login without guest token
    $response = postJson(route('api.v1.auth.password-login'), [
        'identifier' => $user->email,
        'password'   => 'password',
    ], [
        'X-Guest-Token' => Illuminate\Support\Str::uuid7(),
    ]);

    // Assert: Login successful
    $response->assertOk();

    // Assert: User cart remains unchanged
    $userCart->refresh();
    expect($userCart->items)->toHaveCount(1);

    $this->assertDatabaseHas('cart_items', [
        'cart_id'                    => $userCart->id,
        'product_delivery_option_id' => $deliveryOption->id,
        'quantity'                   => 2,
    ]);

});
it('merges guest cart into user cart on password login', function (): void {
    // Arrange: Create a user with an existing cart
    $user     = User::factory()->create(['password' => bcrypt('password')]);
    $userCart = Cart::factory()->create(['user_id' => $user->id]);

    $deliveryOption1 = ProductDeliveryOption::factory()->create();
    CartItem::factory()->create([
        'cart_id'                    => $userCart->id,
        'product_delivery_option_id' => $deliveryOption1->id,
        'quantity'                   => 2,
    ]);

    // Create a guest cart with different items
    $guestToken = Illuminate\Support\Str::uuid()->toString();
    $guestCart  = Cart::factory()->create(['guest_token' => $guestToken]);

    $deliveryOption2 = ProductDeliveryOption::factory()->create();
    CartItem::factory()->create([
        'cart_id'                    => $guestCart->id,
        'product_delivery_option_id' => $deliveryOption2->id,
        'quantity'                   => 3,
    ]);

    // Act: Login with guest token in header
    $response = postJson(route('api.v1.auth.password-login'), [
        'identifier' => $user->email,
        'password'   => 'password',
    ], [
        'X-Guest-Token' => $guestToken,
    ]);

    // Assert: Login successful
    $response->assertOk();

    // Assert: Guest cart is deleted
    $this->assertDatabaseMissing('carts', ['id' => $guestCart->id]);

    // Assert: User cart now has both items
    $userCart->refresh();
    expect($userCart->items)->toHaveCount(2);

    // Assert: Items are correctly merged
    $this->assertDatabaseHas('cart_items', [
        'cart_id'                    => $userCart->id,
        'product_delivery_option_id' => $deliveryOption1->id,
        'quantity'                   => 2,
    ]);

    $this->assertDatabaseHas('cart_items', [
        'cart_id'                    => $userCart->id,
        'product_delivery_option_id' => $deliveryOption2->id,
        'quantity'                   => 3,
    ]);
});

it('keeps user quantity when guest cart has the same single-quantity product', function (): void {
    // Arrange: Create a user with cart containing an item
    $user     = User::factory()->create(['password' => bcrypt('password')]);
    $userCart = Cart::factory()->create(['user_id' => $user->id]);

    $deliveryOption = ProductDeliveryOption::factory()->create();
    CartItem::factory()->create([
        'cart_id'                    => $userCart->id,
        'product_delivery_option_id' => $deliveryOption->id,
        'quantity'                   => 1,
    ]);

    // Create a guest cart with the SAME item
    $guestToken = Illuminate\Support\Str::uuid()->toString();
    $guestCart  = Cart::factory()->create(['guest_token' => $guestToken]);

    CartItem::factory()->create([
        'cart_id'                    => $guestCart->id,
        'product_delivery_option_id' => $deliveryOption->id,
        'quantity'                   => 1,
    ]);

    // Act: Login with guest token in header
    $response = postJson(route('api.v1.auth.password-login'), [
        'identifier' => $user->email,
        'password'   => 'password',
    ], [
        'X-Guest-Token' => $guestToken,
    ]);

    // Assert: Login successful
    $response->assertOk();

    // Assert: Guest cart is deleted
    $this->assertDatabaseMissing('carts', ['id' => $guestCart->id]);

    // Assert: User cart has only 1 item
    $userCart->refresh();
    expect($userCart->items)->toHaveCount(1);

    // Assert: Quantities are NOT summed (single-quantity invariant is preserved)
    $this->assertDatabaseHas('cart_items', [
        'cart_id'                    => $userCart->id,
        'product_delivery_option_id' => $deliveryOption->id,
        'quantity'                   => 1,
    ]);
});

it('sums quantities when merging the same multiple-quantity product', function (): void {
    // Arrange: Create a user with cart containing a multiple-quantity item
    $user     = User::factory()->create(['password' => bcrypt('password')]);
    $userCart = Cart::factory()->create(['user_id' => $user->id]);

    $deliveryOption = ProductDeliveryOption::factory()->create(['allow_multiple_quantity' => true]);
    CartItem::factory()->create([
        'cart_id'                    => $userCart->id,
        'product_delivery_option_id' => $deliveryOption->id,
        'quantity'                   => 2,
    ]);

    // Create a guest cart with the SAME item
    $guestToken = Illuminate\Support\Str::uuid()->toString();
    $guestCart  = Cart::factory()->create(['guest_token' => $guestToken]);

    CartItem::factory()->create([
        'cart_id'                    => $guestCart->id,
        'product_delivery_option_id' => $deliveryOption->id,
        'quantity'                   => 3,
    ]);

    // Act: Login with guest token in header
    $response = postJson(route('api.v1.auth.password-login'), [
        'identifier' => $user->email,
        'password'   => 'password',
    ], [
        'X-Guest-Token' => $guestToken,
    ]);

    // Assert: Login successful
    $response->assertOk();

    // Assert: Guest cart is deleted
    $this->assertDatabaseMissing('carts', ['id' => $guestCart->id]);

    // Assert: User cart has only 1 item
    $userCart->refresh();
    expect($userCart->items)->toHaveCount(1);

    // Assert: Quantities are merged (2 + 3 = 5)
    $this->assertDatabaseHas('cart_items', [
        'cart_id'                    => $userCart->id,
        'product_delivery_option_id' => $deliveryOption->id,
        'quantity'                   => 5,
    ]);
});

it('keeps user item when payment_type differs between user and guest items', function (): void {
    // Arrange: Create a user with cart containing a full-payment item
    $user     = User::factory()->create(['password' => bcrypt('password')]);
    $userCart = Cart::factory()->create(['user_id' => $user->id]);

    $deliveryOption = ProductDeliveryOption::factory()->create(['is_prepayment_available' => true]);
    CartItem::factory()->create([
        'cart_id'                    => $userCart->id,
        'product_delivery_option_id' => $deliveryOption->id,
        'payment_type'               => App\Enums\Order\OrderItemPaymentTypeEnum::FULL_PAYMENT,
        'quantity'                   => 1,
    ]);

    // Create a guest cart with the SAME item but a different payment intent
    $guestToken = Illuminate\Support\Str::uuid()->toString();
    $guestCart  = Cart::factory()->create(['guest_token' => $guestToken]);

    CartItem::factory()->create([
        'cart_id'                    => $guestCart->id,
        'product_delivery_option_id' => $deliveryOption->id,
        'payment_type'               => App\Enums\Order\OrderItemPaymentTypeEnum::PRE_PAYMENT,
        'quantity'                   => 1,
    ]);

    // Act: Login with guest token in header
    $response = postJson(route('api.v1.auth.password-login'), [
        'identifier' => $user->email,
        'password'   => 'password',
    ], [
        'X-Guest-Token' => $guestToken,
    ]);

    // Assert: Login successful
    $response->assertOk();

    // Assert: Guest cart is deleted
    $this->assertDatabaseMissing('carts', ['id' => $guestCart->id]);

    // Assert: User cart has only 1 item, unchanged
    $userCart->refresh();
    expect($userCart->items)->toHaveCount(1);

    $this->assertDatabaseHas('cart_items', [
        'cart_id'                    => $userCart->id,
        'product_delivery_option_id' => $deliveryOption->id,
        'payment_type'               => App\Enums\Order\OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
        'quantity'                   => 1,
    ]);
});

it('converts guest cart to user cart when user has no existing cart', function (): void {
    // Arrange: Create a user WITHOUT a cart
    $user = User::factory()->create(['password' => bcrypt('password')]);

    // Create a guest cart
    $guestToken = Illuminate\Support\Str::uuid()->toString();
    $guestCart  = Cart::factory()->create(['guest_token' => $guestToken]);

    $deliveryOption = ProductDeliveryOption::factory()->create();
    CartItem::factory()->create([
        'cart_id'                    => $guestCart->id,
        'product_delivery_option_id' => $deliveryOption->id,
        'quantity'                   => 5,
    ]);

    $guestCartId = $guestCart->id;

    // Act: Login with guest token in header
    $response = postJson(route('api.v1.auth.password-login'), [
        'identifier' => $user->email,
        'password'   => 'password',
    ], [
        'X-Guest-Token' => $guestToken,
    ]);

    // Assert: Login successful
    $response->assertOk();

    // Assert: Guest cart was converted (same cart ID, but now belongs to user)
    $this->assertDatabaseHas('carts', [
        'id'          => $guestCartId,
        'user_id'     => $user->id,
        'guest_token' => null,
    ]);

    // Assert: Item still exists and belongs to the cart
    $this->assertDatabaseHas('cart_items', [
        'cart_id'                    => $guestCartId,
        'product_delivery_option_id' => $deliveryOption->id,
        'quantity'                   => 5,
    ]);
});

it('merges guest cart on OTP login', function (): void {
    // Arrange: Create a user
    $user = User::factory()->create();

    // Create a guest cart
    $guestToken = Illuminate\Support\Str::uuid()->toString();
    $guestCart  = Cart::factory()->create(['guest_token' => $guestToken]);

    $deliveryOption = ProductDeliveryOption::factory()->create();
    CartItem::factory()->create([
        'cart_id'                    => $guestCart->id,
        'product_delivery_option_id' => $deliveryOption->id,
        'quantity'                   => 2,
    ]);

    // Mock OTP verification
    $otpService = $this->mock(App\Services\OtpManagerService::class);
    $otpService->shouldReceive('verify')->once()->andReturn(true);

    // Act: Verify OTP with guest token in header
    $response = postJson(route('api.v1.auth.otp-verify'), [
        'identifier'    => $user->phone,
        'tracking_code' => 'test-tracking-code',
        'otp_code'      => 1234,
        'otp_type'      => 'SIGNIN',
    ], [
        'X-Guest-Token' => $guestToken,
    ]);

    // Assert: Login successful
    $response->assertOk();

    // Assert: Guest cart is deleted
    $this->assertDatabaseMissing('carts', ['guest_token' => $guestToken]);

    // Assert: User now has a cart with the guest's item
    $this->assertDatabaseHas('cart_items', [
        'product_delivery_option_id' => $deliveryOption->id,
        'quantity'                   => 2,
    ]);
});
