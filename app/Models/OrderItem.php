<?php

namespace App\Models;

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
            'quantity',
            'name',
            'sku',
            'vendor_id',
            'product_data_snapshot_json',
            'price',
            'discount_amount',
            'tax_amount',
            'total',
            'status',
        ];

    protected function casts(): array
    {
        return [
            'product_data_snapshot_json' => 'array',
            'status' => OrderItemStatusEnum::class,
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
