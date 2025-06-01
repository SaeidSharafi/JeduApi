<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Dto\OtpManager\OtpDto;
use App\Enums\OtpType;
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

    public function withOtp(int $code = 123456): self
    {
        return $this->afterCreating(function (Staff $staff) use ($code) {
            $otpService = app(OtpManagerService::class);
            $otpService->send($staff->email, 'staff', OtpType::SIGNIN);
            $otpDto   = new OtpDto($code, $this->trackingCode);
            $cacheKey = sprintf('otp_%s_%s_%s_%s', $staff->email, 'staff', 'value', OtpType::SIGNIN->value);
            cache()->put($cacheKey, $otpDto);
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
