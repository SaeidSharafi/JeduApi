<?php

declare(strict_types=1);

use App\Models\DiscountCoupon;
use App\Models\DiscountPromotion;
use App\Models\DiscountPromotionRule;
use App\Models\ProductDeliveryOption;
use App\Models\ProductDeliveryOptionDiscountPrice;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\Support\Traits\AuthTestTrait;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(AuthTestTrait::class);
beforeEach(function (): void {
    $this->deliveryOption = ProductDeliveryOption::factory()->create([
        'price' => 100000, 'uuid' => Str::uuid()->toString(),
    ]);
});

test('delivery option endpoint exposes pricing and prepayment details', function (): void {
    $this->deliveryOption->update([
        'price'                     => 100000,
        'prepayment_amount'         => 25000,
        'is_prepayment_available'   => true,
        'is_featured'               => true,
        'featured_price'            => 90000,
        'featured_price_start_date' => now()->subDay(),
        'featured_price_end_date'   => now()->addDay(),
    ]);

    $response = getJson('/api/v1/shop/product-delivery-option/'.$this->deliveryOption->uuid);

    $response->assertOk()->assertJsonPath('data.price_data.current_price', 90000);
    expect($response->json('data'))->toMatchArray([
        'price'                   => 100000,
        'prepayment_amount'       => 25000,
        'is_prepayment_available' => true,
    ]);
});

describe('Cart Discount Integration', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create();

        $this->promotion = DiscountPromotion::factory()->create([
            'name'            => 'Test Discount',
            'type'            => App\Enums\Order\DiscountTypeEnum::CART_CHECKOUT,
            'is_active'       => true,
            'starts_at'       => now()->subDay(),
            'ends_at'         => now()->addDay(),
            'priority'        => 1,
            'requires_coupon' => true,
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

    test('cart item exposes current price and product discount details', function (): void {
        $this->deliveryOption->update([
            'prepayment_amount'       => null,
            'is_prepayment_available' => false,
        ]);
        $promotion = DiscountPromotion::factory()->create();
        ProductDeliveryOptionDiscountPrice::factory()->create([
            'product_delivery_option_id' => $this->deliveryOption->id,
            'discount_promotion_id'      => $promotion->id,
            'discounted_price'           => 80000,
        ]);

        $response = postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $this->deliveryOption->uuid,
            'quantity'                     => 1,
        ]);

        $response->assertOk()->assertJsonPath('data.items.0.current_price', 80000);

        expect($response->json('data.items.0'))->toMatchArray([
            'original_price'          => 100000,
            'product_discount_amount' => 20000,
            'cart_discount_amount'    => 0,
            'total_discount_amount'   => 20000,
            'line_total'              => 80000,
            'discount_type'           => 'promotion',
            'discount_percentage'     => 20.0,
            'prepayment_amount'       => null,
            'is_prepayment_available' => false,
        ]);
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
        expect($responsePre->json('data.items.0'))->toMatchArray([
            'current_price'           => 50000,
            'original_price'          => 50000,
            'line_total'              => 10000,
            'prepayment_amount'       => 10000,
            'is_prepayment_available' => true,
        ]);
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
