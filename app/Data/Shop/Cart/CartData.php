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
    ) {}

    public static function fromModel(Cart $cart): self
    {
        return new self(
            id: $cart->id,
            items: CartItemData::collect($cart->items, DataCollection::class),
            total_items_count: $cart->items->count(),
        );
    }
}
