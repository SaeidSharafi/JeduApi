<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\WalletTransactionSourceableContract;
use App\Enums\Order\OrderPaymentStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Services\OrderIncrementIdService;
use App\Traits\HasAuditor;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Order extends Model implements WalletTransactionSourceableContract
{
    use HasAuditor;

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
            'full_value_grand_total',
            'currency_code',
            'applied_coupon_code',
            'applied_cart_discounts_json',
            'admin_notes',
            'created_by',
        ];

    protected $with = ['payments'];

    /**
     * Generate a unique, sequential increment ID for a new order.
     *
     * Uses OrderIncrementIdService for configurable pattern generation
     * with proper transaction locking to prevent race conditions.
     */
    public static function generateIncrementId(): string
    {
        return app(OrderIncrementIdService::class)->generate();
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * Accessor to get the live payment status of the order.
     */
    public function paymentStatus(): Attribute
    {
        return Attribute::make(
            get: function () {
                $totalPaid = $this->total_paid;

                if ($this->grand_total <= 0 && $totalPaid >= 0) {
                    return OrderPaymentStatusEnum::PAID->value;
                }

                if ($totalPaid >= $this->grand_total) {
                    return OrderPaymentStatusEnum::PAID->value;
                }

                if ($totalPaid > 0) {
                    return OrderPaymentStatusEnum::PARTIALLY_PAID->value;
                }

                // Rule 3: If no payment has been made, it's PENDING.
                return OrderPaymentStatusEnum::PENDING->value;
            }
        );
    }

    public function overallPaymentStatus(): Attribute
    {
        return Attribute::make(
            get: function () {
                $totalPaid = $this->total_paid;
                $fullValue = $this->full_value_grand_total;

                if ($fullValue <= 0) {
                    return OrderPaymentStatusEnum::PAID->value;
                }

                if ($totalPaid >= $fullValue) {
                    return OrderPaymentStatusEnum::PAID->value;
                }

                if ($totalPaid > 0) {
                    return OrderPaymentStatusEnum::PARTIALLY_PAID->value;
                }

                return OrderPaymentStatusEnum::PENDING->value;
            }
        );
    }

    protected function casts(): array
    {
        return [
            'customer_snapshot_json'      => 'array',
            'applied_cart_discounts_json' => 'array',
            'subtotal'                    => 'integer',
            'discount_amount'             => 'integer',
            'tax_amount'                  => 'integer',
            'grand_total'                 => 'integer',
            'full_value_grand_total'      => 'integer',
            'status'                      => OrderStatusEnum::class,
            'payment_status'              => OrderPaymentStatusEnum::class,
            'created_at'                  => 'datetime',
            'updated_at'                  => 'datetime',
            'total_paid'                  => 'integer',
        ];
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
            get: fn (): int|float => $this->full_value_grand_total - $this->total_paid,
        );
    }
}
