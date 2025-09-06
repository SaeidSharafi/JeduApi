<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Wallet\WalletStatusEnum;
use App\Traits\HasAuditor;
use Database\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Wallet extends Model
{
    use HasAuditor;

    /** @use HasFactory<WalletFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'balance',
        'gift_balance',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'gift_balance' => 'integer',
            'status' => WalletStatusEnum::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    // Business logic methods
    public function getAvailableBalance(): int
    {
        return $this->balance + $this->gift_balance;
    }

    public function canWithdraw(int $amount): bool
    {
        return $this->status === WalletStatusEnum::ACTIVE && $this->balance >= $amount;
    }

    public function canSpend(int $amount): bool
    {
        return $this->status === WalletStatusEnum::ACTIVE && $this->getAvailableBalance() >= $amount;
    }

    public function isActive(): bool
    {
        return $this->status === WalletStatusEnum::ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === WalletStatusEnum::SUSPENDED;
    }

    public function isClosed(): bool
    {
        return $this->status === WalletStatusEnum::CLOSED;
    }
}
