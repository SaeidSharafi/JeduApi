<?php

namespace Database\Factories;

use App\Dto\OtpManager\OtpDto;
use App\Models\Admin;
use App\Services\OtpManagerService;
use App\Enums\OtpType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminFactory extends Factory
{
    protected $model = Admin::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->phoneNumber(),
            'password' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function withPassword(): self
    {
        return $this->state(fn (array $attributes) => [
            'password' => Hash::make('password123')
        ]);
    }

    public function unverified(): self
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null
        ]);
    }

    public function withOtp(string $code = '123456'): self
    {
        return $this->afterCreating(function (Admin $admin) use ($code) {
            $otpService = app(OtpManagerService::class);
            $otpService->send($admin->email, 'admin', OtpType::SIGNIN);
            $otpDto = new OtpDto($code, $this->trackingCode);
            $cacheKey = sprintf('otp_%s_%s_%s_%s', $admin->email, 'admin', 'value', OtpType::SIGNIN->value);
            cache()->put($cacheKey, $otpDto);
        });
    }
}
