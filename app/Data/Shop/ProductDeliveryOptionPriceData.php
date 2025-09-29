<?php

declare(strict_types=1);

namespace App\Data\Shop;

use Spatie\LaravelData\Data;

final class ProductDeliveryOptionPriceData extends Data
{
    public function __construct(
        public int $current_price,
        public int $original_price,
        public ?int $pre_payment_price,
        public ?int $featured_price,
        public ?int $discount_amount,
        public bool $has_pre_payment_price,
        public bool $has_featured_price,
        public bool $has_discount,
        public ?string $discount_type,
        public ?float $discount_percentage,
        public ?array $range = null,
        public ?string $uuid = null,
    ) {}

    public static function make(
        int $currentPrice,
        int $originalPrice,
        ?int $prePaymentPrice = null,
        ?int $featuredPrice = null,
        ?int $discountAmount = null,
        ?string $discountType = null,
        ?string $uuid = null,

    ): self {
        $hasFeaturedPrice   = $featuredPrice   !== null && $featuredPrice !== $originalPrice;
        $hasDiscount        = $discountAmount  !== null && $discountAmount > 0;
        $hasPrePaymentPrice = $prePaymentPrice !== null && $prePaymentPrice < $originalPrice;
        $discountPercentage = null;
        if ($hasDiscount && $originalPrice > 0) {
            $discountPercentage = round(($discountAmount / $originalPrice) * 100, 2);
        }

        return new self(
            current_price: $currentPrice,
            original_price: $originalPrice,
            pre_payment_price: $prePaymentPrice,
            featured_price: $featuredPrice,
            discount_amount: $discountAmount,
            has_pre_payment_price: $hasPrePaymentPrice,
            has_featured_price: $hasFeaturedPrice,
            has_discount: $hasDiscount,
            discount_type: $discountType,
            discount_percentage: $discountPercentage,
            uuid: $uuid,
        );
    }
}
