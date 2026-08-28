<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EnrollmentStatusEnum;
use App\Enums\ProvisioningStatusEnum;
use App\Events\EnrollmentStatusChanged;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

final class Enrollment extends Model
{
    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory;

    protected $attributes
        = [
            'provisioning_plan'   => '{"version":1,"providers":[],"status":"healthy"}',
            'provisioning_status' => 'healthy',
        ];

    protected $fillable
        = [
            'order_id',
            'order_item_id',
            'customer_id',
            'product_delivery_option_id',
            'enrollment_status',
            'access_start_date',
            'access_end_date',
            'external_enrollment_id',
            'provisioning_data',
            'provisioning_plan',
            'provisioning_status',
            'notes',
        ];

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
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @return BelongsTo<ProductDeliveryOption, $this>
     */
    public function productDeliveryOption(): BelongsTo
    {
        return $this->belongsTo(ProductDeliveryOption::class, 'product_delivery_option_id');
    }

    /**
     * @return HasOneThrough<Product, ProductDeliveryOption, $this>
     */
    public function product(): HasOneThrough
    {
        return $this->hasOneThrough(
            Product::class,
            ProductDeliveryOption::class,
            'id', // Foreign key on ProductDeliveryOption table
            'id', // Foreign key on Product table
            'product_delivery_option_id', // Local key on Enrollment table
            'product_id' // Local key on ProductDeliveryOption table
        );
    }

    /** @return HasMany<ProvisioningAttempt, $this> */
    public function provisioningAttempts(): HasMany
    {
        return $this->hasMany(ProvisioningAttempt::class);
    }

    public function hasRequiredProvisioningProviders(): bool
    {
        return ($this->provisioning_plan['providers'] ?? []) !== [];
    }

    public function activateIfNoProvisioningRequired(): void
    {
        if (! array_key_exists('version', $this->provisioning_plan ?? [])
            || $this->hasRequiredProvisioningProviders()
        ) {
            return;
        }

        $this->forceFill([
            'enrollment_status'   => EnrollmentStatusEnum::ACTIVE,
            'provisioning_status' => ProvisioningStatusEnum::HEALTHY,
        ])->save();
    }

    public function hasHealthyProvisioningOutcomes(): bool
    {
        foreach ($this->provisioning_plan['providers'] ?? [] as $provider) {
            $status = data_get($this->provisioning_data, "providers.{$provider['provider']}.status");

            $isReady              = ($provider['readiness'] ?? null) === 'ready';
            $hasSuccessfulOutcome = in_array($status, ['success', 'waived'], true);

            if (! $isReady || ! $hasSuccessfulOutcome) {
                return false;
            }
        }

        return true;
    }

    protected static function boot(): void
    {
        parent::boot();

        self::saved(function (Enrollment $enrollment): void {
            // Only dispatch when occupancy-relevant data changed. Access dates,
            // notes, provisioning_data and other metadata updates do NOT affect
            // enrolled_count / availability, so dispatching on every save floods
            // the queue with pointless recount + availability jobs.
            if ($enrollment->wasRecentlyCreated
                || $enrollment->wasChanged(['enrollment_status', 'product_delivery_option_id'])
            ) {
                EnrollmentStatusChanged::dispatch($enrollment);
            }
        });

        self::saving(function (Enrollment $enrollment): void {
            if (! $enrollment->requiresProvisioningBeforeActivation()
                || $enrollment->hasHealthyProvisioningOutcomes()) {
                return;
            }

            throw \Illuminate\Validation\ValidationException::withMessages([
                'enrollment_status' => 'Enrollment cannot be activated before provisioning is healthy.',
            ]);
        });

        self::deleting(function (Enrollment $enrollment): void {
            EnrollmentStatusChanged::dispatch($enrollment);
        });
    }

    protected function casts(): array
    {
        return [
            'enrollment_status'   => EnrollmentStatusEnum::class,
            'access_start_date'   => 'date:Y-m-d',
            'access_end_date'     => 'date:Y-m-d',
            'provisioning_data'   => 'array',
            'provisioning_plan'   => 'array',
            'provisioning_status' => ProvisioningStatusEnum::class,
            'created_at'          => 'datetime',
            'updated_at'          => 'datetime',
        ];
    }

    protected function provisioningSummary(): Attribute
    {
        return Attribute::make(
            get: fn (): array => [
                'status'                => $this->provisioning_status,
                'plan'                  => $this->provisioning_plan,
                'reconciliation_status' => data_get($this->provisioning_data, 'reconciliation.status'),
            ],
        );
    }

    private function requiresProvisioningBeforeActivation(): bool
    {
        return $this->exists
            && $this->isDirty('enrollment_status')
            && $this->enrollment_status === EnrollmentStatusEnum::ACTIVE
            && $this->hasRequiredProvisioningProviders();
    }
}
