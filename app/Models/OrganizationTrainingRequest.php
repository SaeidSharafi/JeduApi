<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InboundRequestStatusEnum;
use Database\Factories\OrganizationTrainingRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Plank\Mediable\Mediable;

final class OrganizationTrainingRequest extends Model
{
    /** @use HasFactory<OrganizationTrainingRequestFactory> */
    use HasFactory;

    use Mediable;

    protected $fillable = [
        'uuid',
        'first_name',
        'last_name',
        'phone',
        'position',
        'organization_name',
        'requested_course_names',
        'notes',
        'status',
        'assigned_to_id',
        'vendor_id',
        'vendor_snapshot',
    ];

    /** @return BelongsTo<Staff, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assigned_to_id');
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    protected static function boot(): void
    {
        parent::boot();

        self::creating(function (self $request): void {
            $request->uuid ??= (string) Str::uuid7();
        });
    }

    protected function casts(): array
    {
        return [
            'requested_course_names' => 'array',
            'status'                 => InboundRequestStatusEnum::class,
            'vendor_snapshot'        => 'array',
            'created_at'             => 'datetime',
            'updated_at'             => 'datetime',
        ];
    }
}
