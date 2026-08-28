<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProvisioningAttemptStatusEnum;
use App\Enums\ProvisioningProviderEnum;
use App\Enums\ProvisioningTriggerEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class ProvisioningAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'enrollment_id',
        'provider',
        'trigger',
        'status',
        'sequence',
        'retryable',
        'failure_code',
        'failure_message',
        'failure_metadata',
        'correlation_id',
        'staff_id',
        'queued_at',
        'running_at',
        'succeeded_at',
        'retry_scheduled_at',
        'failed_at',
        'manual_action_required_at',
    ];

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return BelongsTo<Staff, $this> */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    protected static function booted(): void
    {
        self::creating(function (self $attempt): void {
            $attempt->uuid           ??= (string) Str::uuid7();
            $attempt->correlation_id ??= (string) Str::uuid7();
        });
    }

    protected function casts(): array
    {
        return [
            'provider'                  => ProvisioningProviderEnum::class,
            'trigger'                   => ProvisioningTriggerEnum::class,
            'status'                    => ProvisioningAttemptStatusEnum::class,
            'retryable'                 => 'boolean',
            'failure_metadata'          => 'array',
            'queued_at'                 => 'datetime',
            'running_at'                => 'datetime',
            'succeeded_at'              => 'datetime',
            'retry_scheduled_at'        => 'datetime',
            'failed_at'                 => 'datetime',
            'manual_action_required_at' => 'datetime',
        ];
    }
}
