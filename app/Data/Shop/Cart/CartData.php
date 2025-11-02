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
    ) {}

    public static function fromModel(Cart $cart, int $subtotal = 0, int $discountAmount = 0, int $grandTotal = 0): self
    {
        return new self(
            id: $cart->id,
            items: CartItemData::collect($cart->items, DataCollection::class),
            total_items_count: $cart->items->count(),
            applied_coupon_code: $cart->applied_coupon_code,
            subtotal: $subtotal,
            discount_amount: $discountAmount,
            grand_total: $grandTotal,
        );
    }
}
