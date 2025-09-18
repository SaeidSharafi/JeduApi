<?php

declare(strict_types=1);

namespace App\Data\Shop;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

final class ProductPriceData extends Data
{
    public function __construct(
        public int $min_price = 0,
        public int $min_original_price = 0,
        public bool $has_featured_price,
        public bool $has_discount,
        public bool $has_pre_payment,
        public ?string $discount_type,
        public ?float $discount_percentage,
        public ?array $range,
        #[DataCollectionOf(ProductDeliveryOptionPriceData::class)]
        public ?Collection $prices,
    ) {
    }

    /**
     * @param  array<int,ProductDeliveryOptionPriceData>  $prices
     * @param  array|null  $range
     *
     * @return self
     */
    public static function make(
        array $prices,
        ?array $range = null,
    ): self {
        $has_featured_price = false;
        $has_discount = false;
        $has_pre_payment = false;
        $maxDiscountAmount = 0;
        $minDiscountAmount = null;
        $maxPrice = 0;
        $minPrice = null;
        $minOriginalPrice = null;
        $discountType = null;
        $discount_percentage = null;
        foreach ($prices as $price) {
            if ($price->has_pre_payment_price) {
                $has_pre_payment = true;
            }
            if ($price->has_featured_price) {
                $has_featured_price = true;
            }
            if ($price->has_discount) {
                $has_discount = true;
            }

            if ($price->discount_amount !== null) {
                if ($minDiscountAmount === null || $price->discount_amount < $minDiscountAmount) {
                    $minDiscountAmount = $price->discount_amount;
                }
                if ($price->discount_amount > $maxDiscountAmount) {
                    $discountType = $price->discount_type;
                    $maxDiscountAmount = $price->discount_amount;
                    $discount_percentage = $price->discount_percentage;
                }
            }
            if ($price->current_price > $maxPrice) {
                $maxPrice = $price->current_price;
            }
            if ($minPrice === null || $price->current_price < $minPrice) {
                $minPrice = $price->current_price;
            }
            $minOriginalPrice = $minOriginalPrice === null
                ? $price->original_price
                : min($price->original_price, $minOriginalPrice);
        }

        return new self(
            min_price: $minPrice ?? 0,
            min_original_price: $minOriginalPrice ?? 0,
            has_featured_price: $has_featured_price,
            has_discount: $has_discount,
            has_pre_payment: $has_pre_payment,
            discount_type: $discountType,
            discount_percentage: $discount_percentage,
            range: $range,
            prices: collect($prices),
        );
    }
}
