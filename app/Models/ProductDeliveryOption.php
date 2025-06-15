<?php

namespace App\Models;

use App\Enums\DeliveryMethodEnum;
use App\Enums\FulfillmentTypeEnum;
use App\Enums\PublicationStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
            'status'                    => PublicationStatusEnum::class,
            'fulfillment_type'          => FulfillmentTypeEnum::class,
            'delivery_method'           => DeliveryMethodEnum::class,
            'featured_price_start_date' => 'datetime:Y-m-d H:i:s',
            'featured_price_end_date'   => 'datetime:Y-m-d H:i:s',
            'created_at'                => 'datetime:Y-m-d H:i:s',
            'updated_at'                => 'datetime:Y-m-d H:i:s',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class);
    }
}
