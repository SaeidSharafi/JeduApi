<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\AdminActionLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class AdminActionLog extends Model
{
    /** @use HasFactory<AdminActionLogFactory> */
    use HasFactory;

    protected $fillable
        = [
            'admin_id',
            'action_type',
            'resource_type',
            'resource_id',
            'route_name',
            'http_method',
            'request_data',
            'response_status',
            'ip_address',
            'user_agent',
            'session_id',
            'risk_level',
            'metadata',
        ];

    // Relationships
    /**
     * @return BelongsTo<Staff, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'admin_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function resource(): MorphTo
    {
        return $this->morphTo('resource', 'resource_type', 'resource_id');
    }

    // Business Logic
    public function isHighRisk(): bool
    {
        return $this->risk_level === 'high';
    }

    public function isMediumRisk(): bool
    {
        return $this->risk_level === 'medium';
    }

    public function isLowRisk(): bool
    {
        return $this->risk_level === 'low';
    }

    public function isSuccessful(): bool
    {
        return $this->response_status >= 200 && $this->response_status < 300;
    }

    public function isWalletRelated(): bool
    {
        return str_contains($this->route_name, 'wallet')
            || str_contains($this->resource_type, 'Wallet');
    }

    public function getActionSummary(): string
    {
        $action   = ucfirst($this->action_type);
        $resource = $this->resource_type ? class_basename($this->resource_type) : 'Resource';

        return "{$action} {$resource}".($this->resource_id ? " #{$this->resource_id}" : '');
    }

    // Scopes
    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function highRisk(Builder $query): Builder
    {
        return $query->where('risk_level', 'high');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function walletActions(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereLike('route_name', '%wallet%')
                ->orWhereLike('resource_type', '%Wallet%');
        });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function byAdmin(Builder $query, int $adminId): Builder
    {
        return $query->where('admin_id', $adminId);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function byDateRange(Builder $query, string|CarbonInterface $startDate, string|CarbonInterface $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function byRiskLevel(Builder $query, string $riskLevel): Builder
    {
        return $query->where('risk_level', $riskLevel);
    }

    protected function casts(): array
    {
        return [
            'request_data'    => 'array',
            'metadata'        => 'array',
            'risk_level'      => 'string',
            'response_status' => 'integer',
            'created_at'      => 'datetime',
            'updated_at'      => 'datetime',
        ];
    }

    /**
     * @return Attribute<string, string>
     */
    protected function actionSummery(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes): string => $this->getActionSummary(),
        );
    }
}
