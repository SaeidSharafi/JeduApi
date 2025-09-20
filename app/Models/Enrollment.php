<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EnrollmentStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Str;

final class Enrollment extends Model
{
    use HasFactory;

    protected $fillable
        = [
            'order_id',
            'order_item_id',
            'customer_id',
            'product_delivery_option_id',
            'enrollment_status',
            'access_start_date',
            'access_end_date',
            'external_enrollment_id',
            'provisioning_data',
            'notes',
        ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function productDeliveryOption(): BelongsTo
    {
        return $this->belongsTo(ProductDeliveryOption::class, 'product_delivery_option_id');
    }

    public function product(): HasOneThrough
    {
        return $this->hasOneThrough(
            Product::class,
            ProductDeliveryOption::class,
            'id', // Foreign key on ProductDeliveryOption table
            'id', // Foreign key on Product table
            'product_delivery_option_id', // Local key on Enrollment table
            'product_id' // Local key on ProductDeliveryOption table
        );
    }

    protected static function boot(): void
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = (string) Str::uuid7();
        });
    }

    protected function casts(): array
    {
        return [
            'enrollment_status' => EnrollmentStatusEnum::class,
            'access_start_date' => 'date:Y-m-d',
            'access_end_date'   => 'date:Y-m-d',
            'provisioning_data' => 'array',
            'created_at'        => 'datetime',
            'updated_at'        => 'datetime',
        ];
    }
}
