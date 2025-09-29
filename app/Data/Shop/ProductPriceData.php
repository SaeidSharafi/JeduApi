<?php

declare(strict_types=1);

namespace App\Data\Shop;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

final class ProductPriceData extends Data
{
    public function __construct(
        public ?int $min_price,
        public ?int $min_original_price,
        public ?bool $has_featured_price,
        public ?bool $has_discount,
        public ?bool $has_pre_payment,
        public ?string $discount_type,
        public ?float $discount_percentage,
        public ?int $highest_discount_amount,
        public ?array $range,
        #[DataCollectionOf(ProductDeliveryOptionPriceData::class)]
        public ?Collection $prices,
    ) {}

    /**
     * @param  array<int,ProductDeliveryOptionPriceData>  $prices
     */
    public static function make(
        array $prices,
        ?array $range = null,
    ): self {
        if (empty($prices)) {
            return self::makeNoPrice();
        }

        $pricesCollection = collect($prices);

        $bestDiscountOption = $pricesCollection
            ->filter(fn (ProductDeliveryOptionPriceData $p) => $p->discount_amount > 0)
            ->sortByDesc('discount_amount')
            ->first();

        $discountType       = $bestDiscountOption?->discount_type;
        $discountPercentage = $bestDiscountOption?->discount_percentage;

        // --- "Capability Flags" logic (This is the change) ---
        // These flags are now independent of which discount won. They check for presence.
        $hasDiscount = $pricesCollection->some(fn (ProductDeliveryOptionPriceData $p) => $p->discount_type
            === 'promotion');
        $hasFeaturedPrice = $pricesCollection->some(fn (ProductDeliveryOptionPriceData $p) => $p->discount_type
            === 'featured'
            || $p->featured_price !== null);
        $hasPrePayment = $pricesCollection->some('has_pre_payment_price', true);

        // --- Aggregation (This stays the same) ---
        return new self(
            min_price: $pricesCollection->min('current_price'),
            min_original_price: $pricesCollection->min('original_price'),
            has_featured_price: $hasFeaturedPrice, // Now a capability flag
            has_discount: $hasDiscount, // Now a capability flag
            has_pre_payment: $hasPrePayment,
            discount_type: $discountType, // The effective discount type
            discount_percentage: $discountPercentage,
            highest_discount_amount: $bestDiscountOption?->discount_amount,
            range: $range,
            prices: $pricesCollection,
        );
    }

    private static function makeNoPrice(): self
    {
        return new self(
            min_price: 0,
            min_original_price: 0,
            has_featured_price: false,
            has_discount: false,
            has_pre_payment: false,
            discount_type: null,
            discount_percentage: null,
            highest_discount_amount: null,
            range: null,
            prices: collect([]),
        );
    }
}
