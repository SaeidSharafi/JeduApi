<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryMethodEnum;
use App\Enums\FulfillmentTypeEnum;
use App\Enums\PublicationStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class ProductDeliveryOption extends Model
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
            'registration_start_date',
            'registration_end_date',
            'available_from',
            'available_to',
        ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', PublicationStatusEnum::PUBLISHED)
            ->where('available_from', '<=', now())
            ->where('available_to', '>=', now());
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true)
            ->where('featured_price_start_date', '<=', now())
            ->where('featured_price_end_date', '>=', now());
    }

    public function scopePrepaymentAvailable($query)
    {
        return $query->where('is_prepayment_available', true);
    }

    public function scopeRegistrationOpen($query)
    {
        return $query->where('registration_start_date', '<=', now())
            ->where('registration_end_date', '>=', now());
    }

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
}
