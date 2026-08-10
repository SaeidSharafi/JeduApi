<?php

declare(strict_types=1);

namespace App\Data\Shop\Customer;

use App\Enums\User\CivilIdTypeEnum;
use App\Enums\User\EducationLevelEnum;
use App\Enums\User\EducationStatusEnum;
use App\Enums\User\GenderEnum;
use App\Helpers\JalaliDateHelper;
use App\Rules\CivilIdRule;
use App\Rules\UniqueCivilIdRule;
use App\Rules\ValidNormalizedJalaliDateRule;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

final class UpdateProfileData extends Data
{
    public function __construct(
        public string $first_name,
        public string $last_name,
        public ?string $email,
        public ?string $phone2,
        public string $civil_id,
        public string $civil_id_type,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d')]
        public ?Carbon $date_of_birth,
        public string $father_name,
        public string $gender,
        public ?string $education_level,
        public ?string $field_of_study,
        public ?string $education_status,
    ) {}

    public static function prepareForPipeline(array $properties): array
    {
        return JalaliDateHelper::toGregorian($properties, [
            'date_of_birth',
        ]);
    }

    public static function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'email'      => [
                'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore(
                    auth()->user()
                ),
            ],
            'phone2'   => ['nullable', 'string', 'max:15'],
            'civil_id' => [
                'required', 'string', 'max:20', new CivilIdRule(), new UniqueCivilIdRule(auth()->user()?->id),
            ],
            'civil_id_type'    => ['required', 'string', 'max:20', Rule::enum(CivilIdTypeEnum::class)],
            'date_of_birth'    => ['bail', 'required', new ValidNormalizedJalaliDateRule, 'date_format:Y-m-d'],
            'father_name'      => ['required', 'string', 'max:100'],
            'gender'           => ['required', 'string', 'max:10', Rule::enum(GenderEnum::class)],
            'education_level'  => ['nullable', 'string', 'max:20', Rule::enum(EducationLevelEnum::class)],
            'field_of_study'   => ['nullable', 'string', 'max:100'],
            'education_status' => [
                'nullable', 'string', 'max:20', Rule::enum(EducationStatusEnum::class),
            ],
        ];
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'first_name' => ['description' => 'First name of the user'],
            'last_name'  => ['description' => 'Last name of the user'],
            'email'      => ['description' => 'Email address of the user'],
            'phone2'     => ['description' => 'Secondary phone number of the user', 'example' => '09123456789'],
            'civil_id'   => [
                'description' => 'Civil ID of the user **(can not be changed after creation)**',
                'example'     => '1234567890',
            ],
            'civil_id_type' => [
                'description' => 'Type of civil ID **(can not be changed after creation)**',
                'example'     => CivilIdTypeEnum::PASSPORT->value,
            ],
            'date_of_birth' => [
                'description' => 'Date of birth in Jalali format (Y-m-d)',
                'example'     => '1402-01-01',
            ],
            'father_name'     => ['description' => 'Father\'s name of the user', 'example' => 'Hassan'],
            'gender'          => ['description' => 'Gender of the user', 'example' => GenderEnum::MALE->value],
            'education_level' => [
                'description' => 'Education level of the user', 'example' => EducationLevelEnum::BACHELOR->value,
            ],
            'field_of_study'   => ['description' => 'Field of study of the user'],
            'education_status' => [
                'description' => 'Education status of the user', 'example' => EducationStatusEnum::GRADUATED->value,
            ],
        ];

    }
}
