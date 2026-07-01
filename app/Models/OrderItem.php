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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function enrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class, 'order_item_id');
    }

    public function productDeliveryOption(): BelongsTo
    {
        return $this->belongsTo(ProductDeliveryOption::class);
    }

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
     */
    protected function originalPrice(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->pricing_metadata['original_price'] ?? $this->price,
        );
    }

    /**
     * Accessor to get product-level discount amount from pricing_metadata.
     */
    protected function productDiscountAmount(): Attribute
    {
        return Attribute::make(
            get: fn (): int => ($this->pricing_metadata['discount_amount'] ?? 0) * $this->qty_ordered,
        );
    }

    /**
     * Accessor to get total discount (product + cart discounts combined).
     */
    protected function totalDiscountAmount(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->product_discount_amount + $this->discount_amount,
        );
    }
}
