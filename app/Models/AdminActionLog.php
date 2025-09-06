<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AdminActionLogFactory;
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

    // Relationships
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'admin_id');
    }

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
        $action = ucfirst($this->action_type);
        $resource = class_basename($this->resource_type) ?? 'Resource';

        return "{$action} {$resource}".($this->resource_id ? " #{$this->resource_id}" : '');
    }

    protected function actionSummery(): Attribute
    {
        return Attribute::make(
            get: fn($value, array $attributes) => $this->getActionSummary(),
        );
    }
    // Scopes
    public function scopeHighRisk($query)
    {
        return $query->where('risk_level', 'high');
    }

    public function scopeWalletActions($query)
    {
        return $query->where(function ($q) {
            $q->where('route_name', 'like', '%wallet%')
                ->orWhere('resource_type', 'like', '%Wallet%');
        });
    }

    public function scopeByAdmin($query, int $adminId)
    {
        return $query->where('admin_id', $adminId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeByRiskLevel($query, string $riskLevel)
    {
        return $query->where('risk_level', $riskLevel);
    }
}
