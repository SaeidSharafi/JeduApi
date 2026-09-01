<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderItemStatusEnum;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected $fillable
        = [
            'order_id',
            'product_delivery_option_id',
            'vendor_id',
            'name',
            'sku',
            'product_data_snapshot_json',
            'qty_ordered',
            'price',
            'total',
            'payment_type',
            'prepayment_amount',
            'discount_amount',
            'tax_amount',
            'total_refunded',
            'qty_refunded',
            'applied_discount_details_json',
            'pricing_metadata',
            'status',
        ];

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return HasOne<Enrollment, $this>
     */
    public function enrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class, 'order_item_id');
    }

    /**
     * @return BelongsTo<ProductDeliveryOption, $this>
     */
    public function productDeliveryOption(): BelongsTo
    {
        return $this->belongsTo(ProductDeliveryOption::class);
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'order_item_id');
    }

    protected function casts(): array
    {
        return [
            'product_data_snapshot_json'    => 'array',
            'applied_discount_details_json' => 'array',
            'pricing_metadata'              => 'array',
            'status'                        => OrderItemStatusEnum::class,
            'payment_type'                  => OrderItemPaymentTypeEnum::class,
            'created_at'                    => 'datetime',
            'updated_at'                    => 'datetime',
        ];
    }

    /**
     * Accessor to get original price before product-level discounts.
     *
     * @return Attribute<int, never>
     */
    protected function originalPrice(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->pricing_metadata['original_price'] ?? $this->price,
        );
    }

    /**
     * Accessor to get product-level discount amount from pricing_metadata.
     *
     * @return Attribute<int, never>
     */
    protected function productDiscountAmount(): Attribute
    {
        return Attribute::make(
            get: fn (): int => ($this->pricing_metadata['discount_amount'] ?? 0) * $this->qty_ordered,
        );
    }

    /**
     * Accessor to get the effective full-payment unit price captured at checkout.
     *
     * @return Attribute<int, never>
     */
    protected function currentPrice(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->price - ($this->pricing_metadata['discount_amount'] ?? 0),
        );
    }

    /**
     * Accessor to expose whether the delivery option supports prepayment.
     *
     * @return Attribute<bool, never>
     */
    protected function isPrepaymentAvailable(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => (bool) $this->productDeliveryOption?->is_prepayment_available,
        );
    }

    /**
     * Accessor to get total discount (product + cart discounts combined).
     *
     * @return Attribute<int, never>
     */
    protected function totalDiscountAmount(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->product_discount_amount + $this->discount_amount,
        );
    }
}
