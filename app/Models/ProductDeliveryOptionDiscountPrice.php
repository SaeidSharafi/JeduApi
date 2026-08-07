<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Database\Factories\ProductDeliveryOptionDiscountPriceFactory;

final class ProductDeliveryOptionDiscountPrice extends Model
{
    /** @use HasFactory<ProductDeliveryOptionDiscountPriceFactory> */
    use HasFactory;

    /**
     * The primary key is not an auto-incrementing integer.
     */
    public $incrementing = false;

    /**
     * This model's primary key is not 'id'.
     */
    protected $primaryKey = 'product_delivery_option_id';

    /**
     * The attributes that are not mass assignable.
     */
    protected $guarded = [];

    /**
     * The cached price belongs to a parent promotion.
     */
    /**
     * @return BelongsTo<DiscountPromotion, $this>
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(DiscountPromotion::class, 'discount_promotion_id');
    }

    /**
     * The cached price belongs to a specific ProductDeliveryOption.
     */
    /**
     * @return BelongsTo<ProductDeliveryOption, $this>
     */
    public function productDeliveryOption(): BelongsTo
    {
        return $this->belongsTo(ProductDeliveryOption::class, 'product_delivery_option_id');
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
