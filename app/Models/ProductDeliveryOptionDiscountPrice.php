<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductDeliveryOptionDiscountPrice extends Model
{
    /**
     * This model's primary key is not 'id'.
     */
    protected $primaryKey = 'product_delivery_option_id';

    /**
     * The primary key is not an auto-incrementing integer.
     */
    public $incrementing = false;

    /**
     * The attributes that are not mass assignable.
     */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime:Y-m-d H:i:s',
            'updated_at' => 'datetime:Y-m-d H:i:s',
        ];
    }

    /**
     * The cached price belongs to a parent promotion.
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(DiscountPromotion::class, 'discount_promotion_id');
    }

    /**
     * The cached price belongs to a specific ProductDeliveryOption.
     */
    public function productDeliveryOption(): BelongsTo
    {
        return $this->belongsTo(ProductDeliveryOption::class);
    }
}
