<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Order\DiscountTypeEnum;
use Database\Factories\DiscountPromotionFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DiscountPromotion extends Model
{
    /** @use HasFactory<DiscountPromotionFactory> */
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
    /**
     * @return HasMany<DiscountPromotionRule, $this>
     */
    public function rules(): HasMany
    {
        return $this->hasMany(DiscountPromotionRule::class);
    }

    /**
     * A promotion can have many associated coupon codes.
     */
    /**
     * @return HasMany<DiscountCoupon, $this>
     */
    public function coupons(): HasMany
    {
        return $this->hasMany(DiscountCoupon::class);
    }

    /**
     * A promotion can have many pre-calculated prices associated with it.
     */
    /**
     * @return HasMany<ProductDeliveryOptionDiscountPrice, $this>
     */
    public function discountedPrices(): HasMany
    {
        return $this->hasMany(ProductDeliveryOptionDiscountPrice::class, 'discount_promotion_id');
    }

    /**
     * Reservation rows holding this promotion's capacity. Counting the active
     * ones decides the usage caps; see DiscountPromotionUsage::active().
     *
     * @return HasMany<DiscountPromotionUsage, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(DiscountPromotionUsage::class);
    }

    /**
     * Reservation rows whose orders have not settled yet, i.e. capacity that is
     * held rather than consumed. This is the `reserved_usage_count` admins see
     * next to the completion counter; see DiscountPromotionUsage::held().
     *
     * @return HasMany<DiscountPromotionUsage, $this>
     */
    public function heldUsages(): HasMany
    {
        return $this->usages()->held();
    }

    /**
     * {@see self::withReservedUsage()} for an already hydrated model.
     */
    public function loadReservedUsage(): static
    {
        $this->loadCount('heldUsages as reserved_usage_count');

        return $this;
    }

    /**
     * Load the reported reserved capacity with one counted query.
     *
     * The admin payload's `reserved_usage_count` has exactly one definition —
     * `heldUsages` — and this is the only place that chooses it, so no
     * controller can silently report a different scope (the spend cap counts
     * `active()` rows instead, which also include settled usage).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function withReservedUsage(Builder $query): Builder
    {
        return $query->withCount('heldUsages as reserved_usage_count');
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
