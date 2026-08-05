<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Traits\HasAuditor;
use Database\Factories\WalletTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class WalletTransaction extends Model
{
    use HasAuditor;

    /** @use HasFactory<WalletTransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'user_id',
        'type',
        'amount',
        'balance_after',
        'gift_balance_after',
        'source_type',
        'source_id',
        'description',
        'metadata',
        'expires_at',
        'idempotency_key',
    ];

    // Relationships
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Polymorphic relationship for the source
    public function source(): MorphTo
    {
        return $this->morphTo('source', 'source_type', 'source_id');
    }

    // Helper methods
    public function isCredit(): bool
    {
        return $this->amount > 0;
    }

    public function isDebit(): bool
    {
        return $this->amount < 0;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isPromotional(): bool
    {
        return in_array($this->type, [TransactionTypeEnum::GIFT, TransactionTypeEnum::BONUS]);
    }

    protected function casts(): array
    {
        return [
            'amount'             => 'integer',
            'balance_after'      => 'integer',
            'gift_balance_after' => 'integer',
            'type'               => TransactionTypeEnum::class,
            'source_type'        => TransactionSourceEnum::class,
            'metadata'           => 'array',
            'expires_at'         => 'datetime',
            'idempotency_key'    => 'string',
            'created_at'         => 'datetime',
            'updated_at'         => 'datetime',
        ];
    }
}
