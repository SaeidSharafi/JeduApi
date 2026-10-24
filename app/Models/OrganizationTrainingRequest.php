<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InboundRequestStatusEnum;
use Database\Factories\OrganizationTrainingRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Plank\Mediable\Mediable;

final class OrganizationTrainingRequest extends Model
{
    /** @use HasFactory<OrganizationTrainingRequestFactory> */
    use HasFactory;

    use Mediable;

    /**
     * Confusable-free alphabet: no 0/O, no 1/I/L, and no U.
     */
    public const string TRACKING_CODE_ALPHABET = '23456789ABCDEFGHJKMNPQRSTVWXYZ';

    public const string TRACKING_CODE_PREFIX = 'TRN';

    public const int TRACKING_CODE_RANDOM_LENGTH = 8;

    protected $fillable = [
        'tracking_code',
        'first_name',
        'last_name',
        'phone',
        'position',
        'organization_name',
        'requested_course_names',
        'notes',
        'status',
        'assigned_to_id',
    ];

    /**
     * Generate a human-readable public reference such as `TRN-7K4M-QX9P`.
     *
     * The alphabet drops 0/O, 1/I/L, and U so the code survives dictation and
     * handwritten notes; 30^8 combinations keep it unguessable.
     */
    public static function generateTrackingCode(): string
    {
        $code = '';

        for ($position = 0; $position < self::TRACKING_CODE_RANDOM_LENGTH; $position++) {
            $code .= self::TRACKING_CODE_ALPHABET[random_int(0, mb_strlen(self::TRACKING_CODE_ALPHABET) - 1)];
        }

        return self::TRACKING_CODE_PREFIX.'-'.mb_substr($code, 0, 4).'-'.mb_substr($code, 4, 4);
    }

    /** @return BelongsTo<Staff, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assigned_to_id');
    }

    protected static function boot(): void
    {
        parent::boot();

        self::creating(function (self $request): void {
            $request->tracking_code ??= self::generateTrackingCode();
        });
    }

    protected function casts(): array
    {
        return [
            'requested_course_names' => 'array',
            'status'                 => InboundRequestStatusEnum::class,
            'created_at'             => 'datetime',
            'updated_at'             => 'datetime',
        ];
    }
}
