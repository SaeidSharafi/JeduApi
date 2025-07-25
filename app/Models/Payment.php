<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Payment\PaymentStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Payment extends Model
{
    /** @use HasFactory<\Database\Factories\PaymentFactory> */
    use HasFactory;

    protected $fillable
        = [
            'order_id',
            'customer_id',
            'staff_id',
            'amount',
            'method',
            'status',
            'data',
            'admin_notes',
        ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    protected function casts(): array
    {
        return [
            'amount'     => 'integer',
            'status'     => PaymentStatusEnum::class,
            'data'       => 'array',
            'created_at' => 'datetime:Y-m-d H:i:s',
            'updated_at' => 'datetime:Y-m-d H:i:s',
        ];
    }
}
