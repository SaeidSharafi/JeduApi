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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
            'admin_notes',
            'data',
            'created_by',
            'last_gateway_reference',
            'attempt_count',
            'last_attempted_at',
            'ip_address',
            'user_agent',
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

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function latestTransaction(): HasOne
    {
        return $this->hasOne(PaymentTransaction::class)->latestOfMany();
    }

    protected function casts(): array
    {
        return [
            'amount'            => 'integer',
            'method'            => \App\Enums\Payment\PaymentMethodEnum::class,
            'status'            => PaymentStatusEnum::class,
            'data'              => 'array',
            'attempt_count'     => 'integer',
            'last_attempted_at' => 'datetime',
            'created_at'        => 'datetime',
            'updated_at'        => 'datetime',
        ];
    }
}
