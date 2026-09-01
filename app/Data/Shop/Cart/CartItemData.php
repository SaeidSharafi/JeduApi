<?php

declare(strict_types=1);

namespace App\Data\Shop\Cart;

use App\Data\Admin\Discounts\CalculatedOrderItemData;
use App\Data\Shop\ProductDeliveryOptionPriceData;
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
        public int $current_price,
        public int $original_price,
        public int $product_discount_amount,
        public int $cart_discount_amount,
        public int $total_discount_amount,
        public int $line_total,
        public ?int $prepayment_amount,
        public bool $is_prepayment_available,
        public ?string $discount_type,
        public ?float $discount_percentage,
    ) {}

    public static function fromModel(
        CartItem $cartItem,
        CalculatedOrderItemData $calculatedItem,
        ProductDeliveryOptionPriceData $priceData,
    ): self {
        $deliveryOption = $cartItem->productDeliveryOption;
        $product        = $deliveryOption->product;

        return new self(
            id: $cartItem->id,
            product_delivery_option_uuid: $deliveryOption->uuid,
            quantity: $cartItem->quantity,
            payment_type: $cartItem->payment_type,
            product_name: $product->name ?? $deliveryOption->name ?? 'Product',
            sku: $deliveryOption->sku,
            current_price: $priceData->current_price,
            original_price: $priceData->original_price,
            product_discount_amount: $calculatedItem->payment_type === OrderItemPaymentTypeEnum::PRE_PAYMENT
                ? 0
                : ($priceData->discount_amount ?? 0) * $cartItem->quantity,
            cart_discount_amount: $calculatedItem->discount_amount,
            total_discount_amount: $calculatedItem->payment_type === OrderItemPaymentTypeEnum::PRE_PAYMENT
                ? 0
                : (($priceData->discount_amount ?? 0) * $cartItem->quantity) + $calculatedItem->discount_amount,
            line_total: $calculatedItem->total,
            prepayment_amount: $deliveryOption->is_prepayment_available
                ? $deliveryOption->prepayment_amount
                : null,
            is_prepayment_available: $deliveryOption->is_prepayment_available,
            discount_type: $calculatedItem->payment_type === OrderItemPaymentTypeEnum::PRE_PAYMENT
                ? null
                : $priceData->discount_type,
            discount_percentage: $calculatedItem->payment_type === OrderItemPaymentTypeEnum::PRE_PAYMENT
                ? null
                : $priceData->discount_percentage,
        );
    }
}
