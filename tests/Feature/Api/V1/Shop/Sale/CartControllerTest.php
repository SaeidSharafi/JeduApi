<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

uses(Tests\Support\Traits\AuthTestTrait::class);

// ===== Authenticated User Tests =====

describe('index', function (): void {
    it('should return empty cart for authenticated user with no cart', function (): void {
        $user = User::factory()->create();
        $this->customer($user);

        $response = getJson(route('api.v1.shop.cart.index'));

        $response->assertOk();
        $response->assertJsonPath('data.items', []);
        $response->assertJsonPath('data.total_items_count', 0);
    });

    it('should return cart with items for authenticated user', function (): void {
        $user = User::factory()->create();
        $this->customer($user);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create([
            'cart_id'                    => $cart->id,
            'product_delivery_option_id' => ProductDeliveryOption::factory(),
            'quantity'                   => 1,
        ]);
        CartItem::factory()->create([
            'cart_id'                    => $cart->id,
            'product_delivery_option_id' => ProductDeliveryOption::factory(),
            'quantity'                   => 1,
        ]);

        $response = getJson(route('api.v1.shop.cart.index'));

        $response->assertOk();
        $response->assertJsonPath('data.total_items_count', 2);
        $response->assertJsonCount(2, 'data.items');
    });
});

describe('store', function (): void {
    it('should add item to cart for authenticated user', function (): void {
        $user           = User::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create();
        $this->customer($user);

        $response = postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $deliveryOption->uuid,
            'quantity'                     => 1,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.total_items_count', 1);
        $response->assertJsonPath('data.items.0.quantity', 1);

        $this->assertDatabaseHas('carts', ['user_id' => $user->id]);
        $this->assertDatabaseHas('cart_items', [
            'product_delivery_option_id' => $deliveryOption->id,
            'quantity'                   => 1,
        ]);
    });

    it('should throw validation error when adding existing item to cart', function (): void {
        $user           = User::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create();
        $this->customer($user);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create([
            'cart_id'                    => $cart->id,
            'product_delivery_option_id' => $deliveryOption->id,
            'quantity'                   => 1,
        ]);

        $response = postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $deliveryOption->uuid,
            'quantity'                     => 1,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('product_delivery_option_uuid');
    });

    it('should fail with invalid UUID', function (): void {
        $user = User::factory()->create();
        $this->customer($user);

        $response = postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => 'invalid-uuid',
            'quantity'                     => 1,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['product_delivery_option_uuid']);
    });

    it('should fail with non-existent product delivery option', function (): void {
        $user = User::factory()->create();
        $this->customer($user);

        $response = postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => Str::uuid()->toString(),
            'quantity'                     => 1,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['product_delivery_option_uuid']);
    });

    it('should fail with invalid quantity', function (): void {
        $user           = User::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create();
        $this->customer($user);

        $response = postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $deliveryOption->uuid,
            'quantity'                     => 0,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['quantity']);
    });

    it('should fail with quantity exceeding maximum', function (): void {
        $user           = User::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create();
        $this->customer($user);

        $response = postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $deliveryOption->uuid,
            'quantity'                     => 101,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['quantity']);
    });

    it('should reject adding more than one quantity per item even when validation passes', function (): void {
        $user           = User::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create();
        $this->customer($user);

        $response = postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $deliveryOption->uuid,
            'quantity'                     => 2,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['quantity']);
    });
});

describe('update', function (): void {
    it('should update cart item quantity for authenticated user', function (): void {
        $user           = User::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create();
        $this->customer($user);

        $cart     = Cart::factory()->create(['user_id' => $user->id]);
        $cartItem = CartItem::factory()->create([
            'cart_id'                    => $cart->id,
            'product_delivery_option_id' => $deliveryOption->id,
            'quantity'                   => 0, // we set this to 0 since we do not have any product that accept multiple quantities
        ]);

        $response = putJson(route('api.v1.shop.cart.items.update', $cartItem), [
            'quantity' => 1,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.items.0.quantity', 1);

        $this->assertDatabaseHas('cart_items', [
            'id'       => $cartItem->id,
            'quantity' => 1,
        ]);
    });

    it('should fail to update another user\'s cart item', function (): void {
        $user           = User::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create();
        $this->customer($user);

        $otherUser     = User::factory()->create();
        $otherCart     = Cart::factory()->create(['user_id' => $otherUser->id]);
        $otherCartItem = CartItem::factory()->create([
            'cart_id'                    => $otherCart->id,
            'product_delivery_option_id' => $deliveryOption->id,
        ]);

        $response = putJson(route('api.v1.shop.cart.items.update', $otherCartItem), [
            'quantity' => 1,
        ]);

        $response->assertNotFound();
    });

    it('should prevent setting more than 1 quantity for item that does not support it', function (): void {
        $user           = User::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create();
        $this->customer($user);

        $cart     = Cart::factory()->create(['user_id' => $user->id]);
        $cartItem = CartItem::factory()->create([
            'cart_id'                    => $cart->id,
            'product_delivery_option_id' => $deliveryOption->id,
            'quantity'                   => 1,
        ]);

        $response = putJson(route('api.v1.shop.cart.items.update', $cartItem), [
            'quantity' => 2,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['product_delivery_option_uuid']);
    });

});

describe('destroy', function (): void {
    it('should remove item from cart for authenticated user', function (): void {
        $user           = User::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create();
        $this->customer($user);

        $cart     = Cart::factory()->create(['user_id' => $user->id]);
        $cartItem = CartItem::factory()->create([
            'cart_id'                    => $cart->id,
            'product_delivery_option_id' => $deliveryOption->id,
        ]);

        $response = deleteJson(route('api.v1.shop.cart.items.destroy', $cartItem));

        $response->assertNoContent();

        $this->assertDatabaseMissing('cart_items', ['id' => $cartItem->id]);
    });

    it('should fail to delete another user\'s cart item', function (): void {
        $user           = User::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create();
        $this->customer($user);

        $otherUser     = User::factory()->create();
        $otherCart     = Cart::factory()->create(['user_id' => $otherUser->id]);
        $otherCartItem = CartItem::factory()->create([
            'cart_id'                    => $otherCart->id,
            'product_delivery_option_id' => $deliveryOption->id,
        ]);

        $response = deleteJson(route('api.v1.shop.cart.items.destroy', $otherCartItem));

        $response->assertNotFound();
    });
});

describe('CartController - Guest Users', function (): void {
    describe('index', function (): void {
        it('should return empty cart for new guest user', function (): void {
            $response = getJson(route('api.v1.shop.cart.index'));

            $response->assertOk();
            $response->assertJsonPath('data.items', []);
            $response->assertJsonPath('data.total_items_count', 0);
            $response->assertHeader('X-Guest-Token');
        });

        it('should return cart with items for guest user with valid token', function (): void {
            $guestToken = Str::uuid()->toString();

            $cart = Cart::factory()->create(['guest_token' => $guestToken]);
            CartItem::factory()->create([
                'cart_id'                    => $cart->id,
                'product_delivery_option_id' => ProductDeliveryOption::factory(),
            ]);
            CartItem::factory()->create([
                'cart_id'                    => $cart->id,
                'product_delivery_option_id' => ProductDeliveryOption::factory(),
            ]);

            $response = getJson(route('api.v1.shop.cart.index'), [
                'X-Guest-Token' => $guestToken,
            ]);

            $response->assertOk();
            $response->assertJsonPath('data.total_items_count', 2);
            $response->assertJsonCount(2, 'data.items');
            $response->assertHeader('X-Guest-Token', $guestToken);
        });

        it('should generate new token for guest with invalid token', function (): void {
            $response = getJson(route('api.v1.shop.cart.index'), [
                'X-Guest-Token' => 'invalid-token',
            ]);

            $response->assertOk();
            $response->assertHeader('X-Guest-Token');

            $newToken = $response->headers->get('X-Guest-Token');
            expect($newToken)->not->toBe('invalid-token');
            expect(Str::isUuid($newToken))->toBeTrue();
        });
    });

    describe('store', function (): void {
        it('should add item to cart for guest user', function (): void {
            $deliveryOption = ProductDeliveryOption::factory()->create();
            $guestToken     = Str::uuid()->toString();

            $response = postJson(route('api.v1.shop.cart.items.store'), [
                'product_delivery_option_uuid' => $deliveryOption->uuid,
                'quantity'                     => 1,
            ], [
                'X-Guest-Token' => $guestToken,
            ]);

            $response->assertOk();
            $response->assertJsonPath('data.total_items_count', 1);
            $response->assertJsonPath('data.items.0.quantity', 1);
            $response->assertHeader('X-Guest-Token', $guestToken);

            $this->assertDatabaseHas('carts', ['guest_token' => $guestToken]);
            $this->assertDatabaseHas('cart_items', [
                'product_delivery_option_id' => $deliveryOption->id,
                'quantity'                   => 1,
            ]);
        });

        it('should create new cart and generate token for guest without token', function (): void {
            $deliveryOption = ProductDeliveryOption::factory()->create();

            $response = postJson(route('api.v1.shop.cart.items.store'), [
                'product_delivery_option_uuid' => $deliveryOption->uuid,
                'quantity'                     => 1,
            ]);

            $response->assertOk();
            $response->assertHeader('X-Guest-Token');

            $token = $response->headers->get('X-Guest-Token');
            expect(Str::isUuid($token))->toBeTrue();

            $this->assertDatabaseHas('carts', ['guest_token' => $token]);
        });
    });

    describe('update', function (): void {
        it('should update cart item quantity for guest user', function (): void {
            $deliveryOption = ProductDeliveryOption::factory()->create();
            $guestToken     = Str::uuid()->toString();

            $cart     = Cart::factory()->create(['guest_token' => $guestToken]);
            $cartItem = CartItem::factory()->create([
                'cart_id'                    => $cart->id,
                'product_delivery_option_id' => $deliveryOption->id,
                'quantity'                   => 0,
            ]);

            $response = putJson(route('api.v1.shop.cart.items.update', $cartItem), [
                'quantity' => 1,
            ], [
                'X-Guest-Token' => $guestToken,
            ]);

            $response->assertOk();
            $response->assertJsonPath('data.items.0.quantity', 1);
            $response->assertHeader('X-Guest-Token', $guestToken);
        });

        it('should fail to update another guest\'s cart item', function (): void {
            $deliveryOption = ProductDeliveryOption::factory()->create();
            $guestToken     = Str::uuid()->toString();

            $otherToken    = Str::uuid()->toString();
            $otherCart     = Cart::factory()->create(['guest_token' => $otherToken]);
            $otherCartItem = CartItem::factory()->create([
                'cart_id'                    => $otherCart->id,
                'product_delivery_option_id' => $deliveryOption->id,
            ]);

            $response = putJson(route('api.v1.shop.cart.items.update', $otherCartItem), [
                'quantity' => 5,
            ], [
                'X-Guest-Token' => $guestToken,
            ]);

            $response->assertNotFound();
        });
    });

    describe('destroy', function (): void {
        it('should remove item from cart for guest user', function (): void {
            $deliveryOption = ProductDeliveryOption::factory()->create();
            $guestToken     = Str::uuid()->toString();

            $cart     = Cart::factory()->create(['guest_token' => $guestToken]);
            $cartItem = CartItem::factory()->create([
                'cart_id'                    => $cart->id,
                'product_delivery_option_id' => $deliveryOption->id,
            ]);

            $response = deleteJson(route('api.v1.shop.cart.items.destroy', $cartItem), headers: [
                'X-Guest-Token' => $guestToken,
            ]);

            $response->assertNoContent();

            $this->assertDatabaseMissing('cart_items', ['id' => $cartItem->id]);
        });
    });
});
