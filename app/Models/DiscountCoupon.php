<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Database\Factories\DiscountCouponFactory;

final class DiscountCoupon extends Model
{
    /** @use HasFactory<DiscountCouponFactory> */
    use HasFactory;

    /**
     * The attributes that are not mass assignable.
     */
    protected $guarded = [];

    /**
     * Each coupon belongs to a parent promotion.
     */
    /**
     * @return BelongsTo<DiscountPromotion, $this>
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(DiscountPromotion::class, 'discount_promotion_id');
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
