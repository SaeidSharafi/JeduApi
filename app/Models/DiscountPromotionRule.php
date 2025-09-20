<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DiscountPromotionRule extends Model
{
    use HasFactory;

    /**
     * The attributes that are not mass assignable.
     */
    protected $guarded = [];

    /**
     * Each rule belongs to exactly one promotion.
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(DiscountPromotion::class, 'discount_promotion_id');
    }
    /**
     * The attributes that should be cast.
     * The configuration is stored as JSON and will be automatically decoded/encoded.
     */
    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'created_at'    => 'datetime',
            'updated_at'    => 'datetime',
        ];
    }
}
