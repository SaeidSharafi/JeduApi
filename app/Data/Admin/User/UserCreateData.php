<?php

declare(strict_types=1);

namespace App\Data\Admin\User;

use App\Data\Transformer\CarbonFromJalaliString;
use App\Enums\User\CivilIdTypeEnum;
use App\Enums\User\EducationLevelEnum;
use App\Enums\User\EducationStatusEnum;
use App\Enums\User\GenderEnum;
use App\Rules\CivilIdRule;
use App\Rules\UniqueCivilIdRule;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

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
        #[WithCast(CarbonFromJalaliString::class, 'Y-m-d')]
        public ?Carbon $date_of_birth,
        public string $father_name,
        public string $gender,
        public ?string $education_level,
        public ?string $field_of_study,
        public ?string $education_status,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'phone' => [
                'required', 'string', 'max:15',
                Rule::unique('users', 'phone')->ignore(
                    request()->route()?->parameter('user')
                ),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'email'      => [
                'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore(
                    request()->route()?->parameter('user')
                ),
            ],
            'phone2'           => ['nullable', 'string', 'max:15'],
            'civil_id'         => ['required', 'string', 'max:20', new CivilIdRule(), new UniqueCivilIdRule()],
            'civil_id_type'    => ['required', 'string', 'max:20', Rule::enum(CivilIdTypeEnum::class)],
            'date_of_birth'    => ['required', 'jdate:Y-m-d'],
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
            'phone' => [
                'description' => 'The phone number of the user.',
                'example'     => '09123456789',
            ],
            'first_name' => [
                'description' => 'The first name of the user.',
                'example'     => 'John',
            ],
            'last_name' => [
                'description' => 'The last name of the user.',
                'example'     => 'Doe',
            ],
            'email' => [
                'description' => 'The email address of the user.',
                'example'     => 'user@example.com',
            ],
            'phone2' => [
                'description' => 'An optional secondary phone number for the user.',
                'example'     => '09876543210',
            ],
            'civil_id' => [
                'description' => 'The civil ID of the user.',
                'example'     => '123456789',
            ],
            'civil_id_type' => [
                'description' => 'The type of civil ID.',
                'example'     => CivilIdTypeEnum::NATIONAL_CODE->value,
            ],
            'date_of_birth' => [
                'description' => 'The date of birth of the user in Jalali format (Y-m-d).',
                'example'     => '1400-01-01',
            ],
            'father_name' => [
                'description' => 'The name of the user\'s father.',
                'example'     => 'Ali',
            ],
            'gender' => [
                'description' => 'gender of the user.',
                'example'     => GenderEnum::MALE->value,
            ],
            'education_level' => [
                'description' => 'The education level of the user.',
                'example'     => EducationLevelEnum::BACHELOR->value,
            ],
            'field_of_study' => [
                'description' => 'The field of study of the user.',
                'example'     => 'Computer Science',
            ],
            'education_status' => [
                'description' => 'The education status of the user.',
                'example'     => EducationStatusEnum::GRADUATED->value,
            ],
        ];
    }
}
