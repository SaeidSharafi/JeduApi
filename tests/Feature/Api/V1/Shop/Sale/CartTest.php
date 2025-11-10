<?php

declare(strict_types=1);

use App\Models\DiscountCoupon;
use App\Models\DiscountPromotion;
use App\Models\DiscountPromotionRule;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use Illuminate\Support\Str;

use Tests\AuthTestTrait;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

uses(AuthTestTrait::class);
beforeEach(function (): void {
    $this->deliveryOption = ProductDeliveryOption::factory()->create([
        'price' => 100000, 'uuid' => Str::uuid()->toString(),
    ]);
});

describe('Cart Discount Integration', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create();

        $this->promotion = DiscountPromotion::factory()->create([
            'name'      => 'Test Discount',
            'type'      => App\Enums\Order\DiscountTypeEnum::CART_CHECKOUT,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at'   => now()->addDay(),
            'priority'  => 1,
        ]);

        DiscountPromotionRule::create([
            'discount_promotion_id' => $this->promotion->id,
            'type'                  => 'action',
            'handler'               => 'apply_percentage_off',
            'configuration'         => ['percentage' => 10],
        ]);

        $this->coupon = DiscountCoupon::factory()->create([
            'discount_promotion_id' => $this->promotion->id,
            'code'                  => 'SAVE10',
            'is_active'             => true,
        ]);
    });

    test('user can apply valid coupon and see discount in cart', function (): void {
        $this->customer();

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $this->deliveryOption->uuid,
            'quantity'                     => 1,
        ]);

        $response = postJson(route('api.v1.shop.cart.coupon.apply'), [
            'coupon_code' => 'SAVE10',
        ]);

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
        expect($data['applied_coupon_code'])->toBe('SAVE10')
            ->and($data['subtotal'])->toBe(100000)
            ->and($data['discount_amount'])->toBeGreaterThan(0)
            ->and($data['grand_total'])->toBeLessThan($data['subtotal']);

        assertDatabaseHas('carts', [
            'user_id'             => $this->user->id,
            'applied_coupon_code' => 'SAVE10',
        ]);
    });

    test('user receives validation error for invalid coupon', function (): void {
        $this->customer();

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $this->deliveryOption->uuid,
            'quantity'                     => 1,
        ]);

        $response = postJson(route('api.v1.shop.cart.coupon.apply'), [
            'coupon_code' => 'INVALID_COUPON',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('coupon_code');
    });

    test('user can remove applied coupon and totals revert', function (): void {
        $this->customer();

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $this->deliveryOption->uuid,
            'quantity'                     => 1,
        ]);

        postJson(route('api.v1.shop.cart.coupon.apply'), [
            'coupon_code' => 'SAVE10',
        ]);

        $cartWithCoupon = getJson(route('api.v1.shop.cart.index'))->json('data');

        $response = deleteJson(route('api.v1.shop.cart.coupon.remove'));

        $response->assertOk();

        $data = $response->json('data');
        expect($data['applied_coupon_code'])->toBeNull()
            ->and($data['discount_amount'])->toBe(0)
            ->and($data['grand_total'])->toBe($data['subtotal']);

        assertDatabaseHas('carts', [
            'user_id'             => $this->user->id,
            'applied_coupon_code' => null,
        ]);
    });

    test('guest cart shows zero discount_amount without coupon', function (): void {
        $response = postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $this->deliveryOption->uuid,
            'quantity'                     => 1,
        ]);

        $data = $response->json('data');
        expect($data['subtotal'])->toBe(100000)
            ->and($data['discount_amount'])->toBe(0)
            ->and($data['grand_total'])->toBe(100000);
    });
});

describe('Paymetn Type Variations', function (): void {
    it('should allow both prepayment and full_payment when product has pre-payment enabled', function (): void {
        $this->customer();
        $partialPaymentOption = ProductDeliveryOption::factory()->create([
            'price'             => 50000, 'uuid' => Str::uuid()->toString(),
            'prepayment_amount' => 10000, 'is_prepayment_available' => true,
        ]);

        $responseFull = postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $partialPaymentOption->uuid,
            'payment_type'                 => 'full_payment',
            'quantity'                     => 1,
        ]);

        $responseFull->assertOk();
        assertDatabaseHas('cart_items', [
            'payment_type' => 'full_payment',
        ]);
        assertDatabaseCount('cart_items', 1);

        $cartItemId = $responseFull->json('data.items.0.id');

        deleteJson(route('api.v1.shop.cart.items.destroy', $cartItemId));
        $responsePre = postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $partialPaymentOption->uuid,
            'payment_type'                 => 'pre_payment',
            'quantity'                     => 1,
        ]);

        $responsePre->assertOk();
        assertDatabaseHas('cart_items', [
            'payment_type' => 'pre_payment',
        ]);
        assertDatabaseCount('cart_items', 1);

    });

    it('should only allow full_payment when product does not have pre-payment enabled', function (): void {
        $this->customer();
        $fullPaymentOption = ProductDeliveryOption::factory()->create([
            'price'                   => 80000, 'uuid' => Str::uuid()->toString(),
            'is_prepayment_available' => false,
        ]);

        $responsePre = postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $fullPaymentOption->uuid,
            'payment_type'                 => 'pre_payment',
            'quantity'                     => 1,
        ]);

        $responsePre->assertUnprocessable()
            ->assertJsonValidationErrors('payment_type');

        $responseFull = postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $fullPaymentOption->uuid,
            'payment_type'                 => 'full_payment',
            'quantity'                     => 1,
        ]);

        $responseFull->assertOk();
        assertDatabaseHas('cart_items', [
            'payment_type' => 'full_payment',
        ]);
        assertDatabaseCount('cart_items', 1);
    });

});
