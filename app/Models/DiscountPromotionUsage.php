<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Order\OrderStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Links an applied Promotion to the order and customer that used it.
 *
 * The row exists while the order holds a usage slot: pending/processing
 * reserve it, completed/refunded consume it, cancelled/failed release it
 * (the row is deleted). Order status is the single source of truth; a
 * customer's active usage of a Promotion is the count of their usage rows
 * whose order is not cancelled/failed.
 */
final class DiscountPromotionUsage extends Model
{
    /**
     * The attributes that are not mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * @return BelongsTo<DiscountPromotion, $this>
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(DiscountPromotion::class, 'discount_promotion_id');
    }

    /**
     * @return BelongsTo<DiscountCoupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(DiscountCoupon::class, 'discount_coupon_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Rows that still hold the customer's slot: their order is neither
     * cancelled nor failed. Pending/processing reserve, completed/refunded
     * consume.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->whereHas('order', fn (Builder $orderQuery): Builder => $orderQuery->whereNotIn('status', [
            OrderStatusEnum::CANCELLED->value,
            OrderStatusEnum::FAILED->value,
        ]));
    }
}
