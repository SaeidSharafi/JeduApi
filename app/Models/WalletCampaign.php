<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\WalletTransactionSourceableContract;
use App\Enums\Wallet\CampaignTypeEnum;
use App\Traits\HasAuditor;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

final class WalletCampaign extends Model implements WalletTransactionSourceableContract
{
    use HasFactory;
    use HasAuditor;

    protected $fillable
        = [
            'name',
            'description',
            'type',
            'is_active',
            'amount',
            'usage_limit_total',
            'usage_limit_per_user',
            'total_usage_count',
            'starts_at',
            'ends_at',
            'metadata',
            'created_by'
        ];

    protected function casts(): array
    {
        return [
            'type'                 => CampaignTypeEnum::class,
            'is_active'            => 'boolean',
            'amount'               => 'integer',
            'usage_limit_total'    => 'integer',
            'usage_limit_per_user' => 'integer',
            'total_usage_count'    => 'integer',
            'starts_at'            => 'datetime:Y-m-d H:i:s',
            'ends_at'              => 'datetime:Y-m-d H:i:s',
            'metadata'             => 'array',
        ];
    }

    /**
     * All wallet transactions related to this campaign
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'source_id')
            ->where('source_type', 'campaign');
    }

    /**
     * Check if a user can receive allocation from this campaign
     */
    public function canAllocate(User $user): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        if (!$this->isWithinDateRange) {
            return false;
        }

        if ($this->hasReachedTotalLimit()) {
            return false;
        }

        if ($this->hasReachedUserLimit($user)) {
            return false;
        }

        return true;
    }

    /**
     * Check if campaign has reached its total usage limit
     */
    public function hasReachedTotalLimit(): bool
    {
        if ($this->usage_limit_total === null) {
            return false;
        }

        return $this->total_usage_count >= $this->usage_limit_total;
    }

    /**
     * Check if user has reached their usage limit for this campaign
     */
    public function hasReachedUserLimit(User $user): bool
    {
        if ($this->usage_limit_per_user === null) {
            return false;
        }

        $userUsageCount = $this->transactions()
            ->where('user_id', $user->id)
            ->count();

        return $userUsageCount >= $this->usage_limit_per_user;
    }

    /**
     * Check if campaign is currently active
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }


    protected function isWithinDateRange(): Attribute
    {
        return Attribute::make(
            get: function (){
                $now = now();

                if ($this->starts_at && $now->isBefore($this->starts_at)) {
                    return false;
                }

                if ($this->ends_at && $now->isAfter($this->ends_at)) {
                    return false;
                }

                return true;
            }
        );
    }

    /**
     * Increment the total usage count
     */
    public function incrementUsageCount(): void
    {
        $this->increment('total_usage_count');
    }

    /**
     * Get remaining usage count
     */
    public function getRemainingUsageCount(): ?int
    {
        if ($this->usage_limit_total === null) {
            return null;
        }

        return max(0, $this->usage_limit_total - $this->total_usage_count);
    }

    protected function remainingUsageCount(): Attribute
    {
        return Attribute::make(
            get: function (){
                if ($this->usage_limit_total === null) {
                    return null;
                }

                return max(0, $this->usage_limit_total - $this->total_usage_count);
            },
        );
    }
    /**
     * Get user remaining usage count
     */
    public function getUserRemainingUsageCount(User $user): ?int
    {
        if ($this->usage_limit_per_user === null) {
            return null;
        }

        $userUsageCount = $this->transactions()
            ->where('user_id', $user->id)
            ->count();

        return max(0, $this->usage_limit_per_user - $userUsageCount);
    }
}
