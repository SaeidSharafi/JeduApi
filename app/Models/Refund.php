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

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'transaction_details' => 'array',
            'refunded_at'         => 'datetime',
            'status'              => RefundStatusEnum::class,
        ];
    }
}
