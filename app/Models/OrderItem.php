<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderItemStatusEnum;
use Database\Factories\OrderItemFactory;
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

    public function enrolment(): HasOne
    {
        return $this->hasOne(Enrolment::class, 'order_item_id');
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
            'status'                        => OrderItemStatusEnum::class,
            'payment_type'                  => OrderItemPaymentTypeEnum::class,
            'created_at'                    => 'datetime',
            'updated_at'                    => 'datetime',
        ];
    }
}
