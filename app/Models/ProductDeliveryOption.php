<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductDeliveryOption extends Model
{
    use HasFactory;

    protected $fillable
        = [
            'product_id',
            'name',
            'sku',
            'fulfillment_type',
            'delivery_method',
            'price',
            'capacity',
            'status',
            'is_prepayment_available',
            'prepayment_amount',
            'details_json',
            'is_featured',
            'featured_price',
            'featured_price_start_date',
            'featured_price_end_date',
        ];

    protected function casts(): array
    {
        return [
            'is_prepayment_available'   => 'boolean',
            'details_json'              => 'array',
            'is_featured'               => 'boolean',
            'featured_price_start_date' => 'datetime',
            'featured_price_end_date'   => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
