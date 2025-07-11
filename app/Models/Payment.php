<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Payment extends Model
{
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

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => PaymentStatusEnum::class,
            'data'   => 'array',
        ];
    }
}
