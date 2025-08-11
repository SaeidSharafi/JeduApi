<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscountPromotion extends Model
{
    use HasFactory;

    /**
     * The attributes that are not mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'stop_processing_subsequent_rules' => 'boolean',
    ];

    // --- RELATIONSHIPS ---

    /**
     * A promotion consists of multiple rules (conditions and actions).
     */
    public function rules(): HasMany
    {
        return $this->hasMany(DiscountPromotionRule::class);
    }

    /**
     * A promotion can have many associated coupon codes.
     */
    public function coupons(): HasMany
    {
        return $this->hasMany(DiscountCoupon::class);
    }

    /**
     * A promotion can have many pre-calculated prices associated with it.
     */
    public function discountedPrices(): HasMany
    {
        return $this->hasMany(ProductDeliveryOptionDiscountPrice::class);
    }
}
