<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderPaymentStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentStatusEnum;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Order extends Model
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
            'total_item_count',
            'total_qty_ordered',
            'subtotal',
            'discount_amount',
            'tax_amount',
            'grand_total',
            'currency_code',
            'applied_coupon_code',
            'admin_notes',
        ];

    protected $with = ['payments'];

    protected function casts(): array
    {
        return [
            'customer_snapshot_json' => 'array',
            'subtotal'               => 'integer',
            'discount_amount'        => 'integer',
            'tax_amount'             => 'integer',
            'grand_total'            => 'integer',
            'status'                 => OrderStatusEnum::class,
            'payment_status'         => OrderPaymentStatusEnum::class,
            'created_at'             => 'datetime:Y-m-d H:i:s',
            'updated_at'             => 'datetime:Y-m-d H:i:s',
            'total_paid'             => 'integer',
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

        return (string) (((int) $lastOrder->increment_id) + 1);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function enrolments(): HasMany
    {
        return $this->hasMany(Enrolment::class, 'order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
    protected function totalPaid(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->payments()->where('status', 'completed')->sum('amount'),
        );
    }

    /**
     * Accessor to get the current outstanding balance.
     */
    protected function balanceDue(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->grand_total - $this->total_paid,
        );
    }

    /**
     * Accessor to get the live payment status of the order.
     */
    public function paymentStatus(): Attribute
    {
        return Attribute::make(
            get: function () {
                $totalPaid = $this->total_paid;

                if ($totalPaid >= $this->grand_total) {
                    return OrderPaymentStatusEnum::PAID->value;
                }

                if ($totalPaid > 0) {
                    return OrderPaymentStatusEnum::PARTIALLY_PAID->value;
                }

                return OrderPaymentStatusEnum::PENDING->value;
            }
        );

    }
}
