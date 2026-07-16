<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Order\DiscountTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DiscountPromotion extends Model
{
    use HasFactory;

    /**
     * The attributes that are not mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

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
        return $this->hasMany(ProductDeliveryOptionDiscountPrice::class, 'discount_promotion_id');
    }

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active'                        => 'boolean',
            'requires_coupon'                  => 'boolean',
            'starts_at'                        => 'datetime',
            'ends_at'                          => 'datetime',
            'stop_processing_subsequent_rules' => 'boolean',
            'type'                             => DiscountTypeEnum::class,
            'created_at'                       => 'datetime',
            'updated_at'                       => 'datetime',
        ];
    }
}
