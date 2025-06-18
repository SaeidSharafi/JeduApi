<?php

declare(strict_types=1);

namespace App\Data\User;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\CivilIdTypeEnum;
use App\Enums\EducationLevelEnum;
use App\Enums\EducationStatusEnum;
use App\Enums\GenderEnum;
use App\Rules\CivilIdRule;
use App\Rules\UniqueCivilIdRule;
use Hekmatinasser\Verta\Verta;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

final class UserCreateData extends Data
{
    public function __construct(
        public string $phone,
        public string $first_name,
        public string $last_name,
        public ?string $email,
        public ?string $phone2,
        public string $civil_id,
        public string $civil_id_type,
        public string $date_of_birth,
        public string $father_name,
        public string $gender,
        public ?string $education_level,
        public ?string $field_of_study,
        public ?string $education_status,
    ) {
    }

    public static function rules(ValidationContext $context): array
    {
        return [
            'phone'            => ['required', 'string', 'max:15', 'unique:users,phone'],
            'first_name'       => ['required', 'string', 'max:100'],
            'last_name'        => ['required', 'string', 'max:100'],
            'email'            => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone2'           => ['nullable', 'string', 'max:15'],
            'civil_id'         => ['required', 'string', 'max:20', new CivilIdRule(), new UniqueCivilIdRule()],
            'civil_id_type'    => ['required', 'string', 'max:20', Rule::enum(CivilIdTypeEnum::class)],
            'date_of_birth'    => ['required', 'date_format:Y-m-d'],
            'father_name'      => ['required', 'string', 'max:100'],
            'gender'           => ['required', 'string', 'max:10', Rule::enum(GenderEnum::class)],
            'education_level'  => ['nullable', 'string', 'max:20', Rule::enum(EducationLevelEnum::class)],
            'field_of_study'   => ['nullable', 'string', 'max:100'],
            'education_status' => [
                'nullable', 'string', 'max:20', Rule::enum(EducationStatusEnum::class)
            ]
        ];
    }
}
