<?php

namespace App\Models;

use App\Enums\OrderStatusEnum;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable
        = [
            'increment_id',
            'status',
            'customer_id',
            'customer_email',
            'customer_phone',
            'customer_first_name',
            'customer_last_name',
            'customer_snapshot_json',
            'subtotal',
            'discount_amount',
            'tax_amount',
            'grand_total',
            'applied_coupon_code',
            'admin_notes'
        ];

    protected function casts(): array
    {
        return [
            'customer_snapshot_json' => 'array',
            'subtotal'               => 'integer',
            'discount_amount'        => 'integer',
            'tax_amount'             => 'integer',
            'grand_total'            => 'integer',
            'status'                 => OrderStatusEnum::class,
            'created_at'             => 'datetime:Y-m-d H:i:s',
            'updated_at'             => 'datetime:Y-m-d H:i:s',
        ];
    }

    /**
     * Generate a unique, sequential increment ID for a new order.
     */
    public static function generateIncrementId(): string
    {
        // Lock the table for writing to prevent race conditions.
        $lastOrder = self::query()->latest('id')->lockForUpdate()->first();

        if (!$lastOrder) {
            // Starting number for the first order
            return '100000001';
        }

        return (string)(((int)$lastOrder->increment_id) + 1);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
