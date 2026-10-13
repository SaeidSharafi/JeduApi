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
 * (the row is deleted). Order status is the single source of truth for
 * consumption; a held slot additionally goes stale after the configured
 * reservation window, while consumed slots never expire.
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
     * Rows that still count against a customer's allowance.
     *
     * A row counts while its order is neither cancelled nor failed and either
     * its order is settled (completed/refunded/partially refunded — consumed
     * permanently) or the row is younger than the configured reservation
     * window (`discounts.reservation_hold_minutes`). A held row older than the
     * window is stale and stops counting; the order itself is untouched.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        $holdCutoff      = now()->subMinutes((int) config('discounts.reservation_hold_minutes'));
        $settledStatuses = [
            OrderStatusEnum::COMPLETED->value,
            OrderStatusEnum::REFUNDED->value,
            OrderStatusEnum::PARTIALLY_REFUNDED->value,
        ];

        return $query
            ->whereHas('order', fn (Builder $orderQuery): Builder => $orderQuery->whereNotIn('status', [
                OrderStatusEnum::CANCELLED->value,
                OrderStatusEnum::FAILED->value,
            ]))
            ->where(function (Builder $holdQuery) use ($holdCutoff, $settledStatuses): void {
                $holdQuery
                    ->where('created_at', '>=', $holdCutoff)
                    ->orWhereHas('order', fn (Builder $orderQuery): Builder => $orderQuery->whereIn('status', $settledStatuses));
            });
    }
}
