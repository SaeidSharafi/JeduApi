<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Contracts\CartIdentifier;
use App\Data\Shop\Cart\AddCartItemData;
use App\Data\Shop\Cart\UpdateCartItemData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\Bundle;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use App\Services\CartService;
use Mockery\MockInterface;

use function Pest\Laravel\assertDatabaseMissing;

/**
 * Build a Bundle delivery option whose product is a Bundle. FILE-LOCAL fixture
 * (namespaced, so it can never clash with the global makeBundleOffer helper).
 */
function makeBundleCartOption(int $version = 1): ProductDeliveryOption
{
    $bundle  = Bundle::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
    $product = Product::factory()->create([
        'productable_type' => ProductableEnum::BUNDLE->value,
        'productable_id'   => $bundle->id,
        'status'           => PublicationStatusEnum::PUBLISHED,
        'is_visible'       => true,
    ]);

    return ProductDeliveryOption::factory()->create([
        'product_id'          => $product->id,
        'fulfillment_type'    => FulfillmentTypeEnum::COMPOSITE,
        'delivery_method'     => DeliveryMethodEnum::BUNDLE,
        'capacity'            => null,
        'status'              => PublicationStatusEnum::PUBLISHED,
        'composition_version' => $version,
    ]);
}

/**
 * Build a plain (course-backed) delivery option as the non-Bundle control.
 */
function makePlainCartOption(): ProductDeliveryOption
{
    return ProductDeliveryOption::factory()->create(['capacity' => null]);
}

/**
 * Seed a cart item with quantity 0 — the same trick CartControllerTest uses —
 * so a later quantity bump passes the single-quantity guard.
 */
function seedCartItem(Cart $cart, ProductDeliveryOption $pdo, ?int $snapshotVersion = null): CartItem
{
    return CartItem::factory()->create([
        'cart_id'                    => $cart->id,
        'product_delivery_option_id' => $pdo->id,
        'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT,
        'quantity'                   => 0,
        'composition_version'        => $snapshotVersion,
    ]);
}

covers(CartService::class);

describe('CartService', function (): void {

    it('deletes the current cart', function (): void {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);

        $this->mock(CartIdentifier::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('userId')
                ->once()
                ->andReturn($user->id);
            $mock->shouldReceive('guestToken')
                ->zeroOrMoreTimes()
                ->andReturnNull();
        });

        $service = resolve(CartService::class);

        $service->deleteCart();

        assertDatabaseMissing('carts', ['id' => $cart->id]);
    });

    it('does not throw when no cart exists', function (): void {
        $user = User::factory()->create();

        $this->mock(CartIdentifier::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('userId')
                ->once()
                ->andReturn($user->id);
            $mock->shouldReceive('guestToken')
                ->zeroOrMoreTimes()
                ->andReturnNull();
        });

        $service = resolve(CartService::class);

        // Should not throw
        $service->deleteCart();

        $this->assertTrue(true);
    });

    it('snapshots the current composition_version when a Bundle delivery option is added to the cart', function (): void {
        $user = User::factory()->create();
        Cart::factory()->create(['user_id' => $user->id]);
        $this->mock(CartIdentifier::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('userId')
                ->once()
                ->andReturn($user->id);
            $mock->shouldReceive('guestToken')
                ->zeroOrMoreTimes()
                ->andReturnNull();
        });
        $pdo = makeBundleCartOption(version: 1);

        resolve(CartService::class)->addItem(new AddCartItemData(
            product_delivery_option_uuid: $pdo->uuid,
        ));

        $item = CartItem::query()->where('product_delivery_option_id', $pdo->id)->firstOrFail();
        expect((int) $item->composition_version)->toBe((int) $pdo->composition_version);
    });

    it('refreshes the composition_version snapshot when a cart item is updated after a bundle change', function (): void {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $this->mock(CartIdentifier::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('userId')
                ->once()
                ->andReturn($user->id);
            $mock->shouldReceive('guestToken')
                ->zeroOrMoreTimes()
                ->andReturnNull();
        });
        $pdo  = makeBundleCartOption(version: 1);
        $item = seedCartItem($cart, $pdo, snapshotVersion: 1);
        // The Bundle changed after the item was added to the cart.
        $pdo->update(['composition_version' => 2]);

        resolve(CartService::class)->updateItem(
            $item->id,
            new UpdateCartItemData(quantity: 1, payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT),
        );

        $fresh = $item->fresh();
        expect((int) $fresh->composition_version)->toBe(2)
            ->and($fresh->quantity)->toBe(1);
    });

    it('refreshes the composition_version snapshot when the same Bundle option is added again (quantity sum path)', function (): void {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $this->mock(CartIdentifier::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('userId')
                ->once()
                ->andReturn($user->id);
            $mock->shouldReceive('guestToken')
                ->zeroOrMoreTimes()
                ->andReturnNull();
        });
        $pdo  = makeBundleCartOption(version: 1);
        $item = seedCartItem($cart, $pdo, snapshotVersion: 1);
        // The Bundle changed after the item was added to the cart.
        $pdo->update(['composition_version' => 2]);

        resolve(CartService::class)->addItem(new AddCartItemData(
            product_delivery_option_uuid: $pdo->uuid,
            quantity: 1,
        ));

        $fresh = $item->fresh();
        expect($fresh->quantity)->toBe(1)
            ->and((int) $fresh->composition_version)->toBe(2);
    });

    it('keeps composition_version null for a non-Bundle option across add and update', function (): void {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $this->mock(CartIdentifier::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('userId')
                ->times(2)
                ->andReturn($user->id);
            $mock->shouldReceive('guestToken')
                ->zeroOrMoreTimes()
                ->andReturnNull();
        });

        // updateItem path
        $plainUpdate = makePlainCartOption();
        $updateItem  = seedCartItem($cart, $plainUpdate, snapshotVersion: null);

        // addItem quantity-sum path
        $plainAdd = makePlainCartOption();
        $addItem  = seedCartItem($cart, $plainAdd, snapshotVersion: null);

        $service = resolve(CartService::class);
        $service->updateItem(
            $updateItem->id,
            new UpdateCartItemData(quantity: 1, payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT),
        );
        $service->addItem(new AddCartItemData(
            product_delivery_option_uuid: $plainAdd->uuid,
            quantity: 1,
        ));

        expect($updateItem->fresh()->composition_version)->toBeNull()
            ->and($updateItem->fresh()->quantity)->toBe(1)
            ->and($addItem->fresh()->composition_version)->toBeNull()
            ->and($addItem->fresh()->quantity)->toBe(1);
    });

    it('moves guest bundle items into a user cart when no user cart exists yet', function (): void {
        $user      = User::factory()->create();
        $guestCart = Cart::factory()->create(['guest_token' => fake()->uuid(), 'user_id' => null]);
        $pdo       = makeBundleCartOption(version: 1);
        $guestItem = CartItem::factory()->create([
            'cart_id'                    => $guestCart->id,
            'product_delivery_option_id' => $pdo->id,
            'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT,
            'quantity'                   => 1,
            'composition_version'        => 1,
        ]);
        $pdo->update(['composition_version' => 2]);

        resolve(CartService::class)->mergeGuestCart($guestCart->guest_token, $user->id);

        $guestCart->refresh();
        expect($guestCart->user_id)->toBe($user->id)
            ->and($guestCart->guest_token)->toBeNull()
            ->and($guestItem->fresh()->cart_id)->toBe($guestCart->id)
            ->and((int) $guestItem->fresh()->composition_version)->toBe(2);
    });

    it('merges guest quantities only when the product allows multiple quantity and payment types match', function (): void {
        $user      = User::factory()->create();
        $userCart  = Cart::factory()->create(['user_id' => $user->id]);
        $guestCart = Cart::factory()->create(['guest_token' => fake()->uuid()]);
        $product   = Product::factory()->create();
        $pdo       = ProductDeliveryOption::factory()->create([
            'product_id'              => $product->id,
            'allow_multiple_quantity' => true,
            'capacity'                => null,
            'fulfillment_type'        => FulfillmentTypeEnum::COMPOSITE,
            'status'                  => PublicationStatusEnum::PUBLISHED,
        ]);
        $userItem = CartItem::factory()->create([
            'cart_id'                    => $userCart->id,
            'product_delivery_option_id' => $pdo->id,
            'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT,
            'quantity'                   => 1,
            'composition_version'        => null,
        ]);
        $guestItem = CartItem::factory()->create([
            'cart_id'                    => $guestCart->id,
            'product_delivery_option_id' => $pdo->id,
            'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT,
            'quantity'                   => 2,
            'composition_version'        => null,
        ]);

        resolve(CartService::class)->mergeGuestCart($guestCart->guest_token, $user->id);

        $userItem->refresh();
        expect($userItem->quantity)->toBe(3)
            ->and($guestItem->fresh())->toBeNull();
    });

    it('keeps the current user cart item when a guest duplicate has a different payment type', function (): void {
        $user      = User::factory()->create();
        $userCart  = Cart::factory()->create(['user_id' => $user->id]);
        $guestCart = Cart::factory()->create(['guest_token' => fake()->uuid()]);
        $product   = Product::factory()->create();
        $pdo       = ProductDeliveryOption::factory()->create([
            'product_id'              => $product->id,
            'allow_multiple_quantity' => true,
            'capacity'                => null,
            'fulfillment_type'        => FulfillmentTypeEnum::COMPOSITE,
            'status'                  => PublicationStatusEnum::PUBLISHED,
        ]);
        $userItem = CartItem::factory()->create([
            'cart_id'                    => $userCart->id,
            'product_delivery_option_id' => $pdo->id,
            'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT,
            'quantity'                   => 1,
        ]);
        $guestItem = CartItem::factory()->create([
            'cart_id'                    => $guestCart->id,
            'product_delivery_option_id' => $pdo->id,
            'payment_type'               => OrderItemPaymentTypeEnum::PRE_PAYMENT,
            'quantity'                   => 2,
        ]);

        resolve(CartService::class)->mergeGuestCart($guestCart->guest_token, $user->id);

        $userItem->refresh();
        expect($userItem->quantity)->toBe(1)
            ->and($guestCart->fresh())->toBeNull()
            ->and($guestItem->fresh())->toBeNull();
    });
    it('rejects invalid payment and quantity combinations before inserting or updating a cart item', function (): void {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $this->mock(CartIdentifier::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('userId')
                ->zeroOrMoreTimes()
                ->andReturn($user->id);
            $mock->shouldReceive('guestToken')
                ->zeroOrMoreTimes()
                ->andReturnNull();
        });

        $plain    = makePlainCartOption();
        $existing = CartItem::factory()->create([
            'cart_id'                    => $cart->id,
            'product_delivery_option_id' => $plain->id,
            'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT,
            'quantity'                   => 1,
        ]);

        expect(fn () => resolve(CartService::class)->addItem(new AddCartItemData(
            product_delivery_option_uuid: $plain->uuid,
            quantity: 2,
        )))->toThrow(\Illuminate\Validation\ValidationException::class)
            ->and(fn () => resolve(CartService::class)->updateItem(
                $existing->id,
                new UpdateCartItemData(quantity: 2, payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT),
            ))->toThrow(\Illuminate\Validation\ValidationException::class)
            ->and(fn () => resolve(CartService::class)->addItem(new AddCartItemData(
                product_delivery_option_uuid: $plain->uuid,
                payment_type: OrderItemPaymentTypeEnum::PRE_PAYMENT,
            )))->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    it('rejects re-adding a non-multiple-quantity item once quantity is already occupied', function (): void {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $this->mock(CartIdentifier::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('userId')
                ->zeroOrMoreTimes()
                ->andReturn($user->id);
            $mock->shouldReceive('guestToken')
                ->zeroOrMoreTimes()
                ->andReturnNull();
        });

        $plain = makePlainCartOption();
        CartItem::factory()->create([
            'cart_id'                    => $cart->id,
            'product_delivery_option_id' => $plain->id,
            'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT,
            'quantity'                   => 1,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        resolve(CartService::class)->addItem(new AddCartItemData(
            product_delivery_option_uuid: $plain->uuid,
            quantity: 1,
        ));
    });

    it('keeps the user item when guest merge would violate non-multiple quantity rules', function (): void {
        $user      = User::factory()->create();
        $userCart  = Cart::factory()->create(['user_id' => $user->id]);
        $guestCart = Cart::factory()->create(['guest_token' => fake()->uuid()]);
        $product   = Product::factory()->create();
        $pdo       = ProductDeliveryOption::factory()->create([
            'product_id'              => $product->id,
            'allow_multiple_quantity' => false,
            'capacity'                => null,
            'fulfillment_type'        => FulfillmentTypeEnum::COMPOSITE,
            'status'                  => PublicationStatusEnum::PUBLISHED,
        ]);
        $userItem = CartItem::factory()->create([
            'cart_id'                    => $userCart->id,
            'product_delivery_option_id' => $pdo->id,
            'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT,
            'quantity'                   => 1,
        ]);
        $guestItem = CartItem::factory()->create([
            'cart_id'                    => $guestCart->id,
            'product_delivery_option_id' => $pdo->id,
            'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT,
            'quantity'                   => 1,
        ]);

        resolve(CartService::class)->mergeGuestCart($guestCart->guest_token, $user->id);

        expect($userItem->fresh()->quantity)->toBe(1)
            ->and($guestCart->fresh())->toBeNull()
            ->and($guestItem->fresh())->toBeNull();
    });

    it('applies a valid coupon when cart conditions pass', function (): void {
        $user = User::factory()->create();
        $this->mock(CartIdentifier::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('userId')
                ->atLeast()->once()
                ->andReturn($user->id);
            $mock->shouldReceive('guestToken')
                ->zeroOrMoreTimes()
                ->andReturnNull();
        });

        $cart    = Cart::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->create();
        $pdo     = ProductDeliveryOption::factory()->create([
            'product_id'              => $product->id,
            'price'                   => 15000,
            'is_prepayment_available' => true,
            'capacity'                => null,
            'status'                  => PublicationStatusEnum::PUBLISHED,
        ]);
        CartItem::factory()->create([
            'cart_id'                    => $cart->id,
            'product_delivery_option_id' => $pdo->id,
            'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT,
            'quantity'                   => 1,
        ]);

        $promotion = \App\Models\DiscountPromotion::factory()->create([
            'type'            => \App\Enums\Order\DiscountTypeEnum::CART_CHECKOUT,
            'is_active'       => true,
            'requires_coupon' => true,
            'starts_at'       => now()->subDay(),
            'ends_at'         => now()->addDay(),
        ]);
        \App\Models\DiscountCoupon::factory()->create([
            'discount_promotion_id' => $promotion->id,
            'code'                  => 'SAVE10',
            'is_active'             => true,
        ]);

        $applied = resolve(CartService::class)->applyCoupon(new \App\Data\Shop\Cart\ApplyCouponData(coupon_code: 'SAVE10'));
        expect($applied->applied_coupon_code)->toBe('SAVE10')
            ->and($cart->fresh()->applied_coupon_code)->toBe('SAVE10');

        $removed = resolve(CartService::class)->removeCoupon();
        expect($removed->applied_coupon_code)->toBeNull();
    });

    it('rejects a coupon when the promotion exists but cart conditions fail', function (): void {
        $user = User::factory()->create();
        $this->mock(CartIdentifier::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('userId')
                ->atLeast()->once()
                ->andReturn($user->id);
            $mock->shouldReceive('guestToken')
                ->zeroOrMoreTimes()
                ->andReturnNull();
        });

        $cart    = Cart::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->create();
        $pdo     = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price'      => 1000,
            'capacity'   => null,
            'status'     => PublicationStatusEnum::PUBLISHED,
        ]);
        CartItem::factory()->create([
            'cart_id'                    => $cart->id,
            'product_delivery_option_id' => $pdo->id,
            'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT,
            'quantity'                   => 1,
        ]);

        $promotion = \App\Models\DiscountPromotion::factory()->create([
            'type'            => \App\Enums\Order\DiscountTypeEnum::CART_CHECKOUT,
            'is_active'       => true,
            'requires_coupon' => true,
            'starts_at'       => now()->subDay(),
            'ends_at'         => now()->addDay(),
        ]);
        \App\Models\DiscountPromotionRule::create([
            'discount_promotion_id' => $promotion->id,
            'type'                  => 'condition',
            'handler'               => 'cart_value_over',
            'configuration'         => [
                'value'               => 100000,
                'operator'            => '>=',
                'include_prepayments' => true,
            ],
        ]);
        \App\Models\DiscountCoupon::factory()->create([
            'discount_promotion_id' => $promotion->id,
            'code'                  => 'NEED10K',
            'is_active'             => true,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        resolve(CartService::class)->applyCoupon(new \App\Data\Shop\Cart\ApplyCouponData(coupon_code: 'NEED10K'));
    });

    it('returns zero totals for an empty cart and rejects an unknown coupon code', function (): void {
        $user = User::factory()->create();
        $this->mock(CartIdentifier::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('userId')
                ->atLeast()->once()
                ->andReturn($user->id);
            $mock->shouldReceive('guestToken')
                ->zeroOrMoreTimes()
                ->andReturnNull();
        });

        $cart     = Cart::factory()->create(['user_id' => $user->id]);
        $cartData = resolve(CartService::class)->getCart();

        expect($cartData->items)->toHaveCount(0)
            ->and($cartData->subtotal)->toBe(0)
            ->and($cartData->discount_amount)->toBe(0)
            ->and($cartData->grand_total)->toBe(0);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        resolve(CartService::class)->applyCoupon(new \App\Data\Shop\Cart\ApplyCouponData(coupon_code: 'NOPE'));
    });

    it('returns zero totals for an empty cart and blocks prepayment when the option disallows it', function (): void {
        $user = User::factory()->create();
        $this->mock(CartIdentifier::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('userId')
                ->zeroOrMoreTimes()
                ->andReturn($user->id);
            $mock->shouldReceive('guestToken')
                ->zeroOrMoreTimes()
                ->andReturnNull();
        });

        $emptyCart = Cart::factory()->create(['user_id' => $user->id]);
        $emptyData = resolve(CartService::class)->getCart();
        expect($emptyData->items)->toHaveCount(0)
            ->and($emptyData->subtotal)->toBe(0)
            ->and($emptyData->discount_amount)->toBe(0)
            ->and($emptyData->grand_total)->toBe(0);

        $disabledOption = ProductDeliveryOption::factory()->create([
            'capacity'                => null,
            'is_prepayment_available' => false,
        ]);

        $enabledOption = ProductDeliveryOption::factory()->create([
            'capacity'                => null,
            'is_prepayment_available' => true,
        ]);

        expect(fn () => resolve(CartService::class)->addItem(new AddCartItemData(
            product_delivery_option_uuid: $disabledOption->uuid,
            payment_type: OrderItemPaymentTypeEnum::PRE_PAYMENT,
        )))->toThrow(\Illuminate\Validation\ValidationException::class);

        $result = resolve(CartService::class)->addItem(new AddCartItemData(
            product_delivery_option_uuid: $enabledOption->uuid,
            payment_type: OrderItemPaymentTypeEnum::PRE_PAYMENT,
        ));

        expect($result->items)->toHaveCount(1)
            ->and($result->items[0]->payment_type)->toBe(OrderItemPaymentTypeEnum::PRE_PAYMENT);
    });

    it('removes items and clears a previously applied coupon from the cart', function (): void {
        $user = User::factory()->create();
        $this->mock(CartIdentifier::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('userId')
                ->zeroOrMoreTimes()
                ->andReturn($user->id);
            $mock->shouldReceive('guestToken')
                ->zeroOrMoreTimes()
                ->andReturnNull();
        });

        $cart    = Cart::factory()->create(['user_id' => $user->id, 'applied_coupon_code' => 'SAVE10']);
        $product = Product::factory()->create();
        $pdo     = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'capacity'   => null,
            'status'     => PublicationStatusEnum::PUBLISHED,
        ]);
        $item = CartItem::factory()->create([
            'cart_id'                    => $cart->id,
            'product_delivery_option_id' => $pdo->id,
            'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT,
            'quantity'                   => 1,
        ]);

        $removed = resolve(CartService::class)->removeItem($item->id);
        expect($removed->items)->toHaveCount(0)
            ->and($removed->grand_total)->toBe(0);

        $cleared = resolve(CartService::class)->removeCoupon();
        expect($cleared->applied_coupon_code)->toBeNull();
    });

    it('ignores invalid guest-token and empty-guest-cart merge requests', function (): void {
        $user    = User::factory()->create();
        $service = resolve(CartService::class);

        $service->mergeGuestCart('not-a-uuid', $user->id);

        $guestCart = Cart::factory()->create(['guest_token' => fake()->uuid()]);
        $service->mergeGuestCart($guestCart->guest_token, $user->id);

        expect(Cart::query()->where('user_id', $user->id)->count())->toBe(0)
            ->and($guestCart->fresh()->exists())->toBeTrue();
    });
});
