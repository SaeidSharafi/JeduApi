<?php

namespace App\Models;

use App\Enums\EnrolmentStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrolment extends Model
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

    protected function casts(): array
    {
        return [
            'enrollment_status' => EnrolmentStatusEnum::class,
            'access_start_date' => 'date:Y-m-d',
            'access_end_date'   => 'date:Y-m-d',
            'provisioning_data' => 'array',
            'created_at'        => 'datetime:Y-m-d H:i:s',
            'updated_at'        => 'datetime:Y-m-d H:i:s',
        ];
    }

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
}
