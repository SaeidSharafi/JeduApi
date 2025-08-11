<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountPromotionRule extends Model
{
    use HasFactory;

    /**
     * This model does not use the default created_at and updated_at timestamps.
     */
    public $timestamps = false;

    /**
     * The attributes that are not mass assignable.
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast.
     * The configuration is stored as JSON and will be automatically decoded/encoded.
     */
    protected $casts = [
        'configuration' => 'array',
    ];

    // --- RELATIONSHIPS ---

    /**
     * Each rule belongs to exactly one promotion.
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(DiscountPromotion::class, 'discount_promotion_id');
    }
}
