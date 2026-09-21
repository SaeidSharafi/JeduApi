<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Contracts\Cache\CacheStore;
use App\Data\OtpManager\OtpDto;
use App\Enums\System\CacheKey;
use App\Enums\System\OtpType;
use App\Enums\User\CivilIdTypeEnum;
use App\Enums\User\EducationLevelEnum;
use App\Enums\User\EducationStatusEnum;
use App\Enums\User\GenderEnum;
use App\Models\User;
use App\Services\OtpManagerService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

final class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        $civilIdType = $this->faker->randomElement(CivilIdTypeEnum::getAllValues());
        $civilId     = match ($civilIdType) {
            CivilIdTypeEnum::NATIONAL_CODE->value  => $this->faker->numerify('##########'),
            CivilIdTypeEnum::PASSPORT->value       => $this->faker->numerify('#########'),
            CivilIdTypeEnum::IMMIGRANT_CODE->value => $this->faker->numerify('##############'),
        };

        return [
            'first_name'        => $this->faker->firstName,
            'last_name'         => $this->faker->lastName,
            'email'             => $this->faker->unique()->safeEmail(),
            'phone'             => $this->faker->mobile(),
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'password'          => null,
            'civil_id'          => $civilId,
            'civil_id_type'     => $civilIdType,
            'date_of_birth'     => $this->faker->date(max: '1990-01-01'),
            'father_name'       => $this->faker->name,
            'phone2'            => $this->faker->mobile(),
            'gender'            => $this->faker->randomElement(GenderEnum::getAllValues()),
            'education_level'   => $this->faker->randomElement(EducationLevelEnum::getAllValues()),
            'field_of_study'    => $this->faker->persianWord(),
            'education_status'  => $this->faker->randomElement(EducationStatusEnum::getAllValues()),
        ];
    }

    public function withPassword(): self
    {
        return $this->state(fn (array $attributes) => [
            'password' => Hash::make('password123'),
        ]);
    }

    public function unverified(): self
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withNationalCode(): self
    {
        return $this->state(fn (array $attributes) => [
            'civil_id'      => $this->faker->numerify('##########'),
            'civil_id_type' => CivilIdTypeEnum::NATIONAL_CODE->value,
        ]);
    }

    public function withImmigrantCode(): self
    {
        return $this->state(fn (array $attributes) => [
            'civil_id'      => $this->faker->numerify('##############'),
            'civil_id_type' => CivilIdTypeEnum::IMMIGRANT_CODE->value,
        ]);
    }

    public function withPassport(): self
    {
        return $this->state(fn (array $attributes) => [
            'civil_id'      => $this->faker->numerify('#########'),
            'civil_id_type' => CivilIdTypeEnum::PASSPORT->value,
        ]);
    }

    public function withOtp(int $code = 1234): self
    {
        return $this->afterCreating(function (User $user) use ($code): void {
            $sentOtp = app(OtpManagerService::class)->send($user->phone, 'user', OtpType::SIGNIN);

            app(CacheStore::class)->put(
                CacheKey::OtpValue,
                [
                    'identifier' => $user->phone,
                    'guard'      => 'user',
                    'type'       => OtpType::SIGNIN->identifier(),
                ],
                new OtpDto($code, $sentOtp->trackingCode),
            );
        });
    }
}
