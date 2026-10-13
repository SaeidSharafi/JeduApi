<?php

declare(strict_types=1);

namespace App\Data\Shop\Cart;

use App\Models\Cart;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class CartData extends Data
{
    public function __construct(
        public int $id,
        #[DataCollectionOf(CartItemData::class)]
        public DataCollection $items,
        public int $total_items_count,
        public ?string $applied_coupon_code,
        public int $subtotal,
        public int $discount_amount,
        public int $grand_total,
        public ?string $guest_token = null,
        /**
         * Why the stored code is not being used on this cart. A code stays on
         * the cart after it stops being applicable (the coupon was deactivated,
         * its window closed, its cap filled, or the cart no longer meets its
         * conditions); checkout must not be blocked by it, but the customer
         * must not be left wondering why the discount disappeared either.
         */
        public ?string $applied_coupon_error = null,
    ) {}

    public static function fromModel(
        Cart $cart,
        int $subtotal = 0,
        int $discountAmount = 0,
        int $grandTotal = 0,
        ?DataCollection $items = null,
        ?string $appliedCouponError = null,
    ): self {
        return new self(
            id: $cart->id,
            items: $items ?? CartItemData::collect($cart->items, DataCollection::class),
            total_items_count: $cart->items->count(),
            applied_coupon_code: $cart->applied_coupon_code,
            subtotal: $subtotal,
            discount_amount: $discountAmount,
            grand_total: $grandTotal,
            guest_token: $cart->guest_token,
            applied_coupon_error: $appliedCouponError,
        );
    }
}
