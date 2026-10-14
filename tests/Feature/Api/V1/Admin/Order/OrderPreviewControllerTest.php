<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Operators\MatchPolicyEnum;
use App\Enums\Order\DiscountTypeEnum;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\DiscountPromotion;
use App\Models\DiscountPromotionRule;
use App\Models\ProductDeliveryOption;
use App\Models\ProductDeliveryOptionDiscountPrice;
use App\Models\User;
use Tests\Support\Traits\AuthTestTrait;

uses(AuthTestTrait::class);

describe('OrderPreviewController', function (): void {

    it('can preview an order with a product delivery option', function (): void {
        $this->authorized_user([PermissionEnum::ORDER_CREATE->value]);
        $user    = User::factory()->create();
        $product = App\Models\Product::factory()->create([
            'name'   => 'Test Product',
            'status' => PublicationStatusEnum::PUBLISHED,
        ]);
        $category = App\Models\Category::factory()->create();
        $product1 = ProductDeliveryOption::factory()->create([
            'product_id'              => $product->id,
            'status'                  => PublicationStatusEnum::PUBLISHED,
            'price'                   => 50000,
            'is_prepayment_available' => false,
        ]);

        $product2 = ProductDeliveryOption::factory()->create([
            'product_id'              => $product->id,
            'status'                  => PublicationStatusEnum::PUBLISHED,
            'price'                   => 50000,
            'is_prepayment_available' => false,
        ]);
        $product->categories()->attach($category->id);
        $promotion = DiscountPromotion::factory()->create([
            'name'        => 'Test Promotion',
            'description' => 'Test promotion for order preview',
            'type'        => DiscountTypeEnum::PRODUCT_SPECIFIC,
            'is_active'   => true,
            'starts_at'   => now()->subDay(),
            'ends_at'     => now()->addDays(30),
        ]);
        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotion->id,
            'type'                  => 'condition',
            'handler'               => 'product_in_category',
            'configuration'         => [
                'category_ids' => [$category->id],
                'match_policy' => MatchPolicyEnum::ANY,
            ],
        ]);
        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotion->id,
            'type'                  => 'action',
            'handler'               => 'apply_percentage_off_product',
            'configuration'         => [
                'percentage' => 10,
            ],
        ]);
        ProductDeliveryOptionDiscountPrice::create([
            'product_delivery_option_id' => $product1->id,
            'discount_promotion_id'      => $promotion->id,
            'discounted_price'           => 45000, // 10% off
        ]);

        $promotionCart = DiscountPromotion::factory()->create([
            'name'        => 'Test Promotion',
            'description' => 'Test promotion for order preview',
            'type'        => DiscountTypeEnum::CART_CHECKOUT,
            'is_active'   => true,
            'starts_at'   => now()->subDay(),
            'ends_at'     => now()->addDays(30),
        ]);
        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotionCart->id,
            'type'                  => 'action',
            'handler'               => 'apply_percentage_off',
            'configuration'         => [
                'percentage' => 10,
            ],
        ]);
        $promotionCart->coupons()->create([
            'code'        => 'TEST10',
            'is_active'   => true,
            'usage_limit' => 100,
        ]);
        $orderData = [
            'status'      => OrderStatusEnum::PENDING->value,
            'customer_id' => $user->id,
            'items'       => [
                [
                    'product_delivery_option_id' => $product1->id,
                    'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT->value, // Pay in full
                    'qty_ordered'                => 1,
                ],
                [
                    'product_delivery_option_id' => $product2->id,
                    'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT->value, // Pay in full
                    'qty_ordered'                => 1,
                ],
            ],
            'admin_notes'         => 'Test order creation',
            'applied_coupon_code' => 'TEST10',
        ];

        $response = $this->postJson(route('api.v1.admin.orders.preview'), $orderData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'items' => [
                        '*' => [
                            'product_delivery_option' => [
                                'id',
                                'name',
                                'price',
                                'discount_price',
                                'is_prepayment',
                                'prepayment_amount',
                            ],
                            'qty',
                            'payment_type',
                            'price',
                            'total',
                            'discount_amount',
                            'applied_discount_details' => [
                                '*' => [
                                    'promotion_id',
                                    'promotion_name',
                                    'applied_amount',
                                    'coupon_code',
                                ],
                            ],
                        ],
                    ],
                    'subtotal_full_payment_items',
                    'subtotal_all_items',
                    'applied_cart_discounts' => [
                        '*' => [
                            'promotion_id',
                            'promotion_name',
                            'applied_amount',
                            'coupon_code',
                        ],
                    ],
                    'triggered_by_coupon_code',
                ],
                'metadata',
            ]);

    });

    it('keeps the base-priced and full-payment-only subtotals unchanged for a mixed cart', function (): void {
        $this->authorized_user([PermissionEnum::ORDER_CREATE->value]);
        $user    = User::factory()->create();
        $product = App\Models\Product::factory()->create([
            'name'   => 'Mixed Product',
            'status' => PublicationStatusEnum::PUBLISHED,
        ]);

        // Full-payment line: 100,000 list, 80,000 after its product-level discount.
        $fullOption = ProductDeliveryOption::factory()->create([
            'product_id'              => $product->id,
            'status'                  => PublicationStatusEnum::PUBLISHED,
            'price'                   => 100000,
            'is_prepayment_available' => false,
        ]);
        ProductDeliveryOptionDiscountPrice::factory()->create([
            'product_delivery_option_id' => $fullOption->id,
            'discount_promotion_id'      => DiscountPromotion::factory()->create()->id,
            'discounted_price'           => 80000,
        ]);

        // Deposit line: 50,000 course price, 10,000 deposit.
        $depositOption = ProductDeliveryOption::factory()->create([
            'product_id'              => $product->id,
            'status'                  => PublicationStatusEnum::PUBLISHED,
            'price'                   => 50000,
            'is_prepayment_available' => true,
            'prepayment_amount'       => 10000,
        ]);

        $response = $this->postJson(route('api.v1.admin.orders.preview'), [
            'status'      => OrderStatusEnum::PENDING->value,
            'customer_id' => $user->id,
            'items'       => [
                [
                    'product_delivery_option_id' => $fullOption->id,
                    'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                    'qty_ordered'                => 1,
                ],
                [
                    'product_delivery_option_id' => $depositOption->id,
                    'payment_type'               => OrderItemPaymentTypeEnum::PRE_PAYMENT->value,
                    'qty_ordered'                => 1,
                ],
            ],
            'applied_coupon_code' => null,
        ]);

        $response->assertOk();

        // The response contract keeps its base-priced all-lines total
        // (100,000 list + 50,000 list) and its effective full-payment-only
        // total (80,000); neither meaning changed with the new basis.
        expect($response->json('data.subtotal_all_items'))->toBe(150000)
            ->and($response->json('data.subtotal_full_payment_items'))->toBe(80000);
    });

    it('evaluates a cart-value threshold on the same basis as cart and checkout', function (): void {
        $this->authorized_user([PermissionEnum::ORDER_CREATE->value]);
        $user    = User::factory()->create();
        $product = App\Models\Product::factory()->create([
            'name'   => 'Mixed Product',
            'status' => PublicationStatusEnum::PUBLISHED,
        ]);

        $fullOption = ProductDeliveryOption::factory()->create([
            'product_id'              => $product->id,
            'status'                  => PublicationStatusEnum::PUBLISHED,
            'price'                   => 100000,
            'is_prepayment_available' => false,
        ]);
        ProductDeliveryOptionDiscountPrice::factory()->create([
            'product_delivery_option_id' => $fullOption->id,
            'discount_promotion_id'      => DiscountPromotion::factory()->create()->id,
            'discounted_price'           => 80000,
        ]);

        $depositOption = ProductDeliveryOption::factory()->create([
            'product_id'              => $product->id,
            'status'                  => PublicationStatusEnum::PUBLISHED,
            'price'                   => 50000,
            'is_prepayment_available' => true,
            'prepayment_amount'       => 10000,
        ]);

        // 120,000 sits between the effective all-lines value (90,000) and the
        // base-priced all-lines total (150,000), so only the new basis fails it.
        $promotion = DiscountPromotion::factory()->create([
            'type'            => DiscountTypeEnum::CART_CHECKOUT,
            'is_active'       => true,
            'requires_coupon' => true,
            'starts_at'       => now()->subDay(),
            'ends_at'         => now()->addDay(),
        ]);
        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotion->id,
            'type'                  => 'condition',
            'handler'               => 'cart_value_over',
            'configuration'         => [
                'value'                    => 120000,
                'operator'                 => '>=',
                'measure_whole_commitment' => true,
            ],
        ]);
        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotion->id,
            'type'                  => 'action',
            'handler'               => 'apply_percentage_off',
            'configuration'         => ['percentage' => 5],
        ]);
        $promotion->coupons()->create([
            'code'        => 'BASIS120',
            'is_active'   => true,
            'usage_limit' => 100,
        ]);

        $response = $this->postJson(route('api.v1.admin.orders.preview'), [
            'status'      => OrderStatusEnum::PENDING->value,
            'customer_id' => $user->id,
            'items'       => [
                [
                    'product_delivery_option_id' => $fullOption->id,
                    'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                    'qty_ordered'                => 1,
                ],
                [
                    'product_delivery_option_id' => $depositOption->id,
                    'payment_type'               => OrderItemPaymentTypeEnum::PRE_PAYMENT->value,
                    'qty_ordered'                => 1,
                ],
            ],
            'applied_coupon_code' => 'BASIS120',
        ]);

        $response->assertOk();

        // 5% of the discountable 80,000 line would be 4,000 if the base-priced
        // all-lines total still decided the threshold.
        expect($response->json('data.applied_cart_discounts'))->toBe([])
            ->and($response->json('data.items.0.discount_amount'))->toBe(0);
    });
});
