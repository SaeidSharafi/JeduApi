<?php

declare(strict_types=1);

namespace App\Data\Admin\Enrollment;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\EnrollmentRevocationStatusEnum;
use App\Enums\EnrollmentStatusEnum;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

/**
 * Revocation outcome for one Enrollment so support staff can see whether an
 * external provider access removal is still pending, failed, waiting for
 * manual work, or complete.
 */
final class EnrollmentRevocationData extends Data
{
    public function __construct(
        #[MapInputName('id')]
        public int $enrollment_id,
        public string $uuid,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public EnrollmentStatusEnum $enrollment_status,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public ?EnrollmentRevocationStatusEnum $revocation_status = null,
    ) {}
}
