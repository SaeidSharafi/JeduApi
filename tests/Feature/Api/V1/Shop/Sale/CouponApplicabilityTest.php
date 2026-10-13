<?php

declare(strict_types=1);

use App\Enums\Order\DiscountTypeEnum;
use App\Models\DiscountCoupon;
use App\Models\DiscountPromotion;
use App\Models\DiscountPromotionRule;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\Support\Traits\AuthTestTrait;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(AuthTestTrait::class);

/**
 * Create an active cart-checkout promotion with one action rule and one coupon.
 */
function couponApplicabilityPromotion(string $handler, array $configuration): string
{
    $promotion = DiscountPromotion::factory()->create([
        'type'            => DiscountTypeEnum::CART_CHECKOUT,
        'is_active'       => true,
        'priority'        => 1,
        'starts_at'       => now()->subDay(),
        'ends_at'         => now()->addDay(),
        'requires_coupon' => true,
    ]);

    DiscountPromotionRule::create([
        'discount_promotion_id' => $promotion->id,
        'type'                  => 'action',
        'handler'               => $handler,
        'configuration'         => $configuration,
    ]);

    $code = Str::upper(Str::random(8));

    DiscountCoupon::create([
        'discount_promotion_id' => $promotion->id,
        'code'                  => $code,
        'is_active'             => true,
    ]);

    return $code;
}

function couponApplicabilityPrepaymentOption(): ProductDeliveryOption
{
    return ProductDeliveryOption::factory()->create([
        'price'                   => 100000,
        'prepayment_amount'       => 25000,
        'is_prepayment_available' => true,
        'uuid'                    => Str::uuid()->toString(),
    ]);
}

function couponApplicabilityAddDepositItem(ProductDeliveryOption $option): void
{
    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $option->uuid,
        'payment_type'                 => 'pre_payment',
        'quantity'                     => 1,
    ])->assertOk();
}

it('refuses a line-markdown coupon on a deposit-only cart', function (): void {
    $customer = User::factory()->create();
    $this->customer($customer);

    $code = couponApplicabilityPromotion('apply_fixed_amount_off', ['amount' => 3000]);
    couponApplicabilityAddDepositItem(couponApplicabilityPrepaymentOption());

    $response = postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $code]);

    $response->assertStatus(422)->assertJsonValidationErrors(['coupon_code']);
    expect($response->json('errors.coupon_code.0'))->toBe(__('shop.cart.errors.coupon_nothing_discountable'));

    // The coupon is not stored and the deposit keeps its full value.
    assertDatabaseHas('carts', [
        'user_id'             => $customer->id,
        'applied_coupon_code' => null,
    ]);

    $cart = getJson(route('api.v1.shop.cart.index'))->assertOk()->json('data');
    expect($cart['discount_amount'])->toBe(0)
        ->and($cart['grand_total'])->toBe(25000);
});

it('still accepts a wallet-credit coupon on a deposit-only cart', function (): void {
    $customer = User::factory()->create();
    $this->customer($customer);

    $code = couponApplicabilityPromotion('add_wallet_credit', ['amount' => 5000]);
    couponApplicabilityAddDepositItem(couponApplicabilityPrepaymentOption());

    $data = postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $code])
        ->assertOk()
        ->json('data');

    expect($data['applied_coupon_code'])->toBe($code)
        ->and($data['discount_amount'])->toBe(0)
        ->and($data['grand_total'])->toBe(25000);
});

it('still accepts a gift-credit coupon on a deposit-only cart', function (): void {
    $customer = User::factory()->create();
    $this->customer($customer);

    $code = couponApplicabilityPromotion('add_gift_credit', ['amount' => 5000]);
    couponApplicabilityAddDepositItem(couponApplicabilityPrepaymentOption());

    postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $code])->assertOk();
});

it('accepts a markdown coupon when a fully paid line is present', function (): void {
    $customer = User::factory()->create();
    $this->customer($customer);

    $code = couponApplicabilityPromotion('apply_fixed_amount_off', ['amount' => 3000]);
    couponApplicabilityAddDepositItem(couponApplicabilityPrepaymentOption());

    $fullOption = ProductDeliveryOption::factory()->create([
        'price' => 40000,
        'uuid'  => Str::uuid()->toString(),
    ]);
    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $fullOption->uuid,
        'payment_type'                 => 'full_payment',
        'quantity'                     => 1,
    ])->assertOk();

    postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $code])->assertOk();
});

it('leaves applying a coupon to an empty cart unaffected', function (): void {
    $this->customer();

    $code = couponApplicabilityPromotion('apply_fixed_amount_off', ['amount' => 3000]);

    // An empty cart is not validated against discountability: the promotion is
    // found and stored without a line to mark down.
    postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $code])
        ->assertOk()
        ->assertJsonPath('data.applied_coupon_code', $code);
});

it('refuses a line-markdown coupon on a bundle-only cart', function (): void {
    $customer = User::factory()->create();
    $this->customer($customer);

    $code = couponApplicabilityPromotion('apply_fixed_amount_off', ['amount' => 3000]);

    $course       = v11CoursePair(100000);
    $bundleOption = v11BundleFor([['pdo' => $course['pdo_a'], 'allocation' => 200000]]);

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $bundleOption->uuid,
        'quantity'                     => 1,
    ])->assertOk();

    $response = postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $code]);

    $response->assertStatus(422)->assertJsonValidationErrors(['coupon_code']);
    expect($response->json('errors.coupon_code.0'))->toBe(__('shop.cart.errors.coupon_nothing_discountable'));

    // The coupon is not stored and the bundle line keeps its full value.
    assertDatabaseHas('carts', [
        'user_id'             => $customer->id,
        'applied_coupon_code' => null,
    ]);

    $cart = getJson(route('api.v1.shop.cart.index'))->assertOk()->json('data');
    $line = $cart['items'][0];

    expect($cart['discount_amount'])->toBe(0)
        ->and($cart['grand_total'])->toBe(200000)
        ->and($line['cart_discount_amount'])->toBe(0)
        ->and($line['line_total'])->toBe(200000);
});
