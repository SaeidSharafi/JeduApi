<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\WalletTransactionSourceableContract;
use App\Enums\Order\RefundStatusEnum;
use App\Traits\HasAuditor;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Refund extends Model implements WalletTransactionSourceableContract
{
    use HasAuditor;

    /** @use HasFactory<RefundFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts
        = [
            'transaction_details' => 'array',
            'refunded_at'         => 'datetime',
            'status'              => RefundStatusEnum::class,
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
        return $this->belongsTo(User::class);
    }
}
