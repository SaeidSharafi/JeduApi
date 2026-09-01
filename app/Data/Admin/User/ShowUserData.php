<?php

declare(strict_types=1);

namespace App\Data\Admin\User;

use App\Data\Casts\AdvancedDateTimeInterfaceCast;
use App\Data\Transformer\AdvancedDateTimeInterfaceTransformer;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\User\CivilIdTypeEnum;
use App\Enums\User\EducationLevelEnum;
use App\Enums\User\EducationStatusEnum;
use App\Enums\User\GenderEnum;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

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
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public ?CivilIdTypeEnum $civil_id_type,
        #[WithTransformer(AdvancedDateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public ?Verta $date_of_birth,
        public ?string $father_name,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public ?GenderEnum $gender,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public ?EducationLevelEnum $education_level,
        public ?string $field_of_study,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public ?EducationStatusEnum $education_status,
        public bool $is_banned = false,
        #[WithCast(AdvancedDateTimeInterfaceCast::class), WithTransformer(AdvancedDateTimeInterfaceTransformer::class)]
        public ?Verta $banned_at = null,
        public array $media = []
    ) {}
}
