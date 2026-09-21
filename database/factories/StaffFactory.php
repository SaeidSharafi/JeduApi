<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Contracts\Cache\CacheStore;
use App\Data\OtpManager\OtpDto;
use App\Enums\System\CacheKey;
use App\Enums\System\OtpType;
use App\Models\Staff;
use App\Services\OtpManagerService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class StaffFactory extends Factory
{
    protected $model = Staff::class;

    public function definition(): array
    {
        return [
            'name'           => $this->faker->name(),
            'email'          => $this->faker->unique()->safeEmail(),
            'phone'          => $this->faker->unique()->mobile(),
            'password'       => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function withPassword(): self
    {
        return $this->state(fn (array $attributes) => [
            'password' => Hash::make(Str::random(10)),
        ]);
    }

    public function unverified(): self
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withOtp(int $code = 1234): self
    {
        return $this->afterCreating(function (Staff $staff) use ($code): void {
            $sentOtp = app(OtpManagerService::class)->send($staff->phone, 'staff', OtpType::SIGNIN);

            app(CacheStore::class)->put(
                CacheKey::OtpValue,
                [
                    'identifier' => $staff->phone,
                    'guard'      => 'staff',
                    'type'       => OtpType::SIGNIN->identifier(),
                ],
                new OtpDto($code, $sentOtp->trackingCode),
            );
        });
    }

    public function isSuperAdmin(): self
    {
        return $this->afterCreating(function (Staff $staff) {
            $staff->is_admin = true;
            $staff->save();
            $staff->refresh();
        });
    }
}
