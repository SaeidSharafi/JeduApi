<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProductPrice extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'product_id',
        'min_price',
        'min_original_price',
        'max_price',
        'max_original_price',
        'has_discount',
        'has_featured_price',
        'has_prepayment',
        'discount_percentage',
        'highest_discount_amount',
    ];

    /**
     * The product this price data belongs to.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Check if the product has any active discounts.
     */
    public function hasActiveDiscount(): bool
    {
        return $this->has_discount || $this->has_featured_price;
    }

    /**
     * Get the effective minimum price (after discounts).
     */
    public function getEffectiveMinPrice(): int
    {
        return $this->min_price;
    }

    /**
     * Get the price range if there's a difference between min and max.
     */
    public function getPriceRange(): array
    {
        return [
            'min' => $this->min_price,
            'max' => $this->max_price,
        ];
    }

    /**
     * Get discount amount (difference between original and discounted price).
     */
    public function getDiscountAmount(): int
    {
        return $this->min_original_price - $this->min_price;
    }

    /**
     * Check if this is a single-price product (min = max).
     */
    public function isSinglePrice(): bool
    {
        return $this->min_price === $this->max_price;
    }

    /**
     * Scope for products with discounts.
     */
    public function scopeWithDiscount($query)
    {
        return $query->where('has_discount', true);
    }

    /**
     * Scope for products with featured prices.
     */
    public function scopeWithFeaturedPrice($query)
    {
        return $query->where('has_featured_price', true);
    }

    /**
     * Scope for products within a price range.
     */
    public function scopePriceRange($query, ?int $minPrice = null, ?int $maxPrice = null)
    {
        if ($minPrice !== null) {
            $query->where('min_price', '>=', $minPrice);
        }

        if ($maxPrice !== null) {
            $query->where('max_price', '<=', $maxPrice);
        }

        return $query;
    }

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'product_id'              => 'integer',
            'min_price'               => 'integer',
            'min_original_price'      => 'integer',
            'max_price'               => 'integer',
            'max_original_price'      => 'integer',
            'has_discount'            => 'boolean',
            'has_featured_price'      => 'boolean',
            'has_prepayment'          => 'boolean',
            'discount_percentage'     => 'decimal:2',
            'highest_discount_amount' => 'integer',
            'created_at'              => 'datetime',
            'updated_at'              => 'datetime',
        ];
    }
}
