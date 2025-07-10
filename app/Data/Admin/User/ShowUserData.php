<?php

declare(strict_types=1);

namespace App\Data\Admin\User;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\CivilIdTypeEnum;
use App\Enums\EducationLevelEnum;
use App\Enums\EducationStatusEnum;
use App\Enums\GenderEnum;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

final class ShowUserData extends Data
{
    public function __construct(
        public int $id,
        public string $phone,
        public ?string $first_name,
        public ?string $last_name,
        public ?string $email,
        public ?string $phone2,
        public ?string $civil_id,
        #[WithCast(EnumCast::class),WithTransformer(TranslatableEnumData::class)]
        public CivilIdTypeEnum $civil_id_type,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d')]
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public ?Verta $date_of_birth,
        public ?string $father_name,
        #[WithCast(EnumCast::class),WithTransformer(TranslatableEnumData::class)]
        public ?GenderEnum $gender,
        #[WithCast(EnumCast::class),WithTransformer(TranslatableEnumData::class)]
        public ?EducationLevelEnum $education_level,
        public ?string $field_of_study,
        #[WithCast(EnumCast::class),WithTransformer(TranslatableEnumData::class)]
        public ?EducationStatusEnum $education_status,
    ) {}
}
