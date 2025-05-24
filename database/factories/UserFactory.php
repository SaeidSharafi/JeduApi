<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Dto\OtpManager\OtpDto;
use App\Enums\OtpType;
use App\Models\User;
use App\Services\OtpManagerService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'phone'             => fake()->unique()->numerify('09########'),
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'password'          => null,
            'remember_token'    => Str::random(10),
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

    public function withOtp(int $code = 123456): self
    {
        return $this->afterCreating(function (User $user) use ($code) {
            $otpService = app(OtpManagerService::class);
            $otpService->send($user->email, 'user', OtpType::SIGNIN);
            $otpDto   = new OtpDto($code, $this->trackingCode);
            $cacheKey = sprintf('otp_%s_%s_%s_%s', $user->email, 'user', 'value', OtpType::SIGNIN->value);
            cache()->put($cacheKey, $otpDto);
        });
    }
}
