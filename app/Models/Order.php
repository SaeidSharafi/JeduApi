<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderPaymentStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Traits\HasAuditor;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

final class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;
    use HasAuditor;

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
            'full_value_grand_total',
            'currency_code',
            'applied_coupon_code',
            'admin_notes',
            'created_by',
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
            'full_value_grand_total' => 'integer',
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
            get: function () {
                // Check if the attribute from our 'withSum' query exists.
                // The name laravel creates is {relation}_{function}_{column}
                if (isset($this->completed_payments_sum_amount)) {
                    // If it exists, just return the pre-calculated value. No new query is run.
                    return (int) $this->completed_payments_sum_amount;
                }

                // Fallback for when the sum wasn't eager-loaded (e.g., on a 'show' page).
                // This assumes the 'payments' relationship has already been loaded.
                return $this->payments->where('status', 'completed')->sum('amount');
            }
        );
    }

    /**
     * Accessor to get the current outstanding balance.
     */
    protected function balanceDue(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->full_value_grand_total - $this->total_paid,
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

                if ($this->full_value_grand_total <= 0) {
                    return OrderPaymentStatusEnum::PAID->value;
                }

                // Rule 1: If the total paid meets or exceeds the TRUE full value, it's PAID.
                if ($totalPaid >= $this->full_value_grand_total) {
                    return OrderPaymentStatusEnum::PAID->value;
                }

                // Rule 2: If ANY payment has been made, it's PARTIALLY_PAID.
                // This now correctly covers pre-payment orders where total_paid > 0 but is less than the full_value_total.
                if ($totalPaid > 0) {
                    return OrderPaymentStatusEnum::PARTIALLY_PAID->value;
                }

                // Rule 3: If no payment has been made, it's PENDING.
                return OrderPaymentStatusEnum::PENDING->value;
            }
        );

    }
}
