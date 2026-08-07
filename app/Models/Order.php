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
use Illuminate\Database\Eloquent\Relations\HasOne;

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
            'total_refunded',
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

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasOne<Payment, $this>
     */
    public function firstPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->oldestOfMany();
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'order_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'order_id');
    }

    /**
     * Accessor to get the live payment status of the order.
     *
     * @return Attribute<string, never>
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

    /**
     * @return Attribute<string, never>
     */
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
            'total_refunded'              => 'integer',
        ];
    }

    /**
     * @return Attribute<int, never>
     */
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
     *
     * NOTE: As of the current implementation, pre-payment courses settle their
     * remainder offline (in-person at our station), so PENDING orders always
     * have balance_due === full_value_grand_total. Partial online payments are
     * not yet enabled. This accessor exists for future installment / online
     * rest-payment features. When installments ship, revisit retry-payment
     * flows and the provisioning trigger config.
     *
     * @return Attribute<int, never>
     */
    protected function balanceDue(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->full_value_grand_total - $this->total_paid,
        );
    }

    /**
     * @return Attribute<int, never>
     */
    protected function netRevenue(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->total_paid - (int) ($this->total_refunded ?? 0),
        );
    }

    /**
     * Accessor to get total product-level discount (featured price + auto-promotions).
     * Sums product_discount_amount from all order items.
     *
     * @return Attribute<int, never>
     */
    protected function totalProductDiscount(): Attribute
    {
        return Attribute::make(
            get: fn (): int => (int) $this->items->sum('product_discount_amount'),
        );
    }

    /**
     * Accessor to get total cart-level discount (coupons).
     * This is an alias for discount_amount for clarity.
     *
     * @return Attribute<int, never>
     */
    protected function totalCartDiscount(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->discount_amount,
        );
    }

    /**
     * Accessor to get total of all discounts (product + cart).
     *
     * @return Attribute<int, never>
     */
    protected function totalDiscount(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->total_product_discount + $this->total_cart_discount,
        );
    }
}
