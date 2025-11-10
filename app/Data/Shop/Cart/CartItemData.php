<?php

declare(strict_types=1);

namespace App\Data\Shop\Cart;

use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Models\CartItem;
use Spatie\LaravelData\Data;

final class CartItemData extends Data
{
    public function __construct(
        public int $id,
        public string $product_delivery_option_uuid,
        public int $quantity,
        public OrderItemPaymentTypeEnum $payment_type,
        public string $product_name,
        public string $sku,
    ) {}

    public static function fromModel(CartItem $cartItem): self
    {
        $deliveryOption = $cartItem->productDeliveryOption;
        $product        = $deliveryOption->product;

        return new self(
            id: $cartItem->id,
            product_delivery_option_uuid: $deliveryOption->uuid,
            quantity: $cartItem->quantity,
            payment_type: $cartItem->payment_type,
            product_name: $product->name ?? $deliveryOption->name ?? 'Product',
            sku: $deliveryOption->sku,
        );
    }
}
