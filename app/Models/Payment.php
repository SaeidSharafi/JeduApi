<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\WalletTransactionSourceableContract;
use App\Enums\Payment\PaymentStatusEnum;
use App\Traits\HasAuditor;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Payment extends Model implements WalletTransactionSourceableContract
{
    use HasAuditor;
    /** @use HasFactory<\Database\Factories\PaymentFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable
        = [
            'order_id',
            'customer_id',
            'amount',
            'method',
            'status',
            'data',
            'admin_notes',
            'created_by',
        ];

    /**
     * Get the columns that should receive a unique identifier.
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

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
            'method'     => \App\Enums\Payment\PaymentMethodEnum::class,
            'status'     => PaymentStatusEnum::class,
            'data'       => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
