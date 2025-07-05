<?php

namespace App\Models;

use App\Enums\OrderItemPaymentTypeEnum;
use App\Enums\OrderItemStatusEnum;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
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
            'status'
        ];

    protected function casts(): array
    {
        return [
            'product_data_snapshot_json' => 'array',
            'status' => OrderItemStatusEnum::class,
            'payment_type' => OrderItemPaymentTypeEnum::class,
            'created_at' => 'datetime:Y-m-d H:i:s',
            'updated_at' => 'datetime:Y-m-d H:i:s',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
