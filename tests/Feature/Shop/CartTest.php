<?php

declare(strict_types=1);

use App\Models\DiscountCoupon;
use App\Models\DiscountPromotion;
use App\Models\DiscountPromotionRule;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function (): void {
    // Create test product delivery option
    $this->deliveryOption = ProductDeliveryOption::factory()->create([
        'price' => 100000, // 100,000 IRR
        'uuid'  => Str::uuid()->toString(),
    ]);
});

describe('Guest Cart', function (): void {
    test('guest user can create a cart and add items without authentication', function (): void {
        // Act: Add item to cart without authentication
        $response = postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $this->deliveryOption->uuid,
            'quantity'                     => 2,
        ]);

        // Assert: Response contains X-Guest-Token
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'items',
                    'total_items_count',
                    'applied_coupon_code',
                    'subtotal',
                    'discount_amount',
                    'grand_total',
                ],
            ]);

        expect($response->headers->has('X-Guest-Token'))->toBeTrue();

        $guestToken = $response->headers->get('X-Guest-Token');

        // Assert: Cart was created in database
        assertDatabaseHas('carts', [
            'guest_token' => $guestToken,
            'user_id'     => null,
        ]);
    });

    test('guest user can use token to manage their cart across requests', function (): void {
        // Arrange: Create cart and get guest token
        $response = postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $this->deliveryOption->uuid,
            'quantity'                     => 1,
        ]);

        $guestToken = $response->headers->get('X-Guest-Token');

        // Act: Get cart using guest token
        $getResponse = getJson(route('api.v1.shop.cart.index'), ['X-Guest-Token' => $guestToken]);

        // Assert: Cart is retrieved correctly
        $getResponse->assertOk()
            ->assertJson([
                'data' => [
                    'total_items_count' => 1,
                ],
            ]);

        // Act: Update cart item quantity
        $cartItemId = $getResponse->json('data.items.0.id');

        $updateResponse = putJson(route('api.v1.shop.cart.items.update', $cartItemId), [
            'quantity' => 3,
        ], ['X-Guest-Token' => $guestToken]);

        // Assert: Quantity updated
        $updateResponse->assertOk()
            ->assertJson([
                'data' => [
                    'total_items_count' => 1,
                ],
            ]);

        // Act: Delete cart item
        $deleteResponse = deleteJson(route('api.v1.shop.cart.items.destroy', $cartItemId), [], ['X-Guest-Token' => $guestToken]);

        // Assert: Item deleted
        $deleteResponse->assertNoContent();

        // Verify cart is now empty
        $finalGetResponse = getJson(route('api.v1.shop.cart.index'), ['X-Guest-Token' => $guestToken]);
        $finalGetResponse->assertOk()
            ->assertJson([
                'data' => [
                    'total_items_count' => 0,
                    'subtotal'          => 0,
                    'discount_amount'   => 0,
                    'grand_total'       => 0,
                ],
            ]);
    });
});

describe('Authenticated Cart', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create();
    });

    test('authenticated user can manage cart without guest token', function (): void {
        // Arrange: Authenticate user
        $this->actingAs($this->user, 'user');

        // Act: Add item to cart
        $response = postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $this->deliveryOption->uuid,
            'quantity'                     => 2,
        ]);

        // Assert: No X-Guest-Token returned for authenticated user
        $response->assertOk();
        expect($response->headers->has('X-Guest-Token'))->toBeFalse();

        // Assert: Cart created for user
        assertDatabaseHas('carts', [
            'user_id'     => $this->user->id,
            'guest_token' => null,
        ]);

        // Act: Get cart
        $getResponse = getJson(route('api.v1.shop.cart.index'));

        // Assert: Cart data correct
        $getResponse->assertOk()
            ->assertJson([
                'data' => [
                    'total_items_count' => 1,
                ],
            ]);
    });

    test('authenticated user can update cart item quantity', function (): void {
        // Arrange
        $this->actingAs($this->user, 'user');

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $this->deliveryOption->uuid,
            'quantity'                     => 1,
        ]);

        $cart       = $this->user->cart;
        $cartItemId = $cart->items->first()->id;

        // Act: Update quantity
        $response = putJson(route('api.v1.shop.cart.items.update', $cartItemId), [
            'quantity' => 5,
        ]);

        // Assert
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'total_items_count' => 1,
                ],
            ]);

        assertDatabaseHas('cart_items', [
            'id'       => $cartItemId,
            'quantity' => 5,
        ]);
    });

    test('authenticated user can delete cart item', function (): void {
        // Arrange
        $this->actingAs($this->user, 'user');

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $this->deliveryOption->uuid,
            'quantity'                     => 1,
        ]);

        $cart       = $this->user->cart;
        $cartItemId = $cart->items->first()->id;

        // Act: Delete item
        $response = deleteJson(route('api.v1.shop.cart.items.destroy', $cartItemId));

        // Assert
        $response->assertNoContent();

        $this->assertDatabaseMissing('cart_items', [
            'id' => $cartItemId,
        ]);
    });
});

describe('Cart Discount Integration', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create();

        // Create a discount promotion with coupon
        $this->promotion = DiscountPromotion::factory()->create([
            'name'      => 'Test Discount',
            'type'      => App\Enums\Order\DiscountTypeEnum::CART_CHECKOUT,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at'   => now()->addDay(),
            'priority'  => 1,
        ]);

        // Add a percentage discount action
        DiscountPromotionRule::create([
            'discount_promotion_id' => $this->promotion->id,
            'type'                  => 'action',
            'handler'               => 'apply_percentage_off',
            'configuration'         => ['percentage' => 10], // 10% discount
        ]);

        // Create coupon
        $this->coupon = DiscountCoupon::factory()->create([
            'discount_promotion_id' => $this->promotion->id,
            'code'                  => 'SAVE10',
            'is_active'             => true,
        ]);
    });

    test('user can apply valid coupon and see discount in cart', function (): void {
        // Arrange: User with cart item
        $this->actingAs($this->user, 'user');

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $this->deliveryOption->uuid,
            'quantity'                     => 1,
        ]);

        // Act: Apply coupon
        $response = postJson(route('api.v1.shop.cart.coupon.apply'), [
            'coupon_code' => 'SAVE10',
        ]);

        // Assert: Discount applied
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'applied_coupon_code',
                    'subtotal',
                    'discount_amount',
                    'grand_total',
                ],
            ]);

        $data = $response->json('data');
        expect($data['applied_coupon_code'])->toBe('SAVE10');
        expect($data['subtotal'])->toBe(100000);
        expect($data['discount_amount'])->toBeGreaterThan(0);
        expect($data['grand_total'])->toBeLessThan($data['subtotal']);

        // Verify coupon saved in database
        assertDatabaseHas('carts', [
            'user_id'             => $this->user->id,
            'applied_coupon_code' => 'SAVE10',
        ]);
    });

    test('user receives validation error for invalid coupon', function (): void {
        // Arrange: User with cart item
        $this->actingAs($this->user, 'user');

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $this->deliveryOption->uuid,
            'quantity'                     => 1,
        ]);

        // Act: Apply invalid coupon
        $response = postJson(route('api.v1.shop.cart.coupon.apply'), [
            'coupon_code' => 'INVALID_COUPON',
        ]);

        // Assert: Validation error
        $response->assertUnprocessable()
            ->assertJsonValidationErrors('coupon_code');
    });

    test('user can remove applied coupon and totals revert', function (): void {
        // Arrange: User with cart and applied coupon
        $this->actingAs($this->user, 'user');

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $this->deliveryOption->uuid,
            'quantity'                     => 1,
        ]);

        postJson(route('api.v1.shop.cart.coupon.apply'), [
            'coupon_code' => 'SAVE10',
        ]);

        // Get cart with coupon applied
        $cartWithCoupon = getJson(route('api.v1.shop.cart.index'))->json('data');

        // Act: Remove coupon
        $response = deleteJson(route('api.v1.shop.cart.coupon.remove'));

        // Assert: Coupon removed
        $response->assertOk();

        $data = $response->json('data');
        expect($data['applied_coupon_code'])->toBeNull();
        expect($data['discount_amount'])->toBe(0);
        expect($data['grand_total'])->toBe($data['subtotal']);

        // Verify coupon removed from database
        assertDatabaseHas('carts', [
            'user_id'             => $this->user->id,
            'applied_coupon_code' => null,
        ]);
    });

    test('guest cart shows zero discount_amount without coupon', function (): void {
        // Act: Guest adds item
        $response = postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $this->deliveryOption->uuid,
            'quantity'                     => 2,
        ]);

        // Assert: No discount for guest carts (no user-specific promotions)
        $data = $response->json('data');
        expect($data['subtotal'])->toBe(200000); // 2 * 100,000
        expect($data['discount_amount'])->toBe(0);
        expect($data['grand_total'])->toBe(200000);
    });
});
