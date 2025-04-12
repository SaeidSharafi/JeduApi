<?php

namespace Database\Factories;

use App\Models\Otp;
use Illuminate\Database\Eloquent\Factories\Factory;

class OtpFactory extends Factory
{
    protected $model = Otp::class;

    public function definition(): array
    {
        return [
            'identifier' => fake()->email(),
            'type' => 'email',
            'code' => '123456',
            'expires_at' => now()->addMinutes(5),
            'purpose' => 'LOGIN'
        ];
    }

    public function expired(): self
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subMinutes(6)
        ]);
    }

    public function forPasswordReset(): self
    {
        return $this->state(fn (array $attributes) => [
            'purpose' => 'PASSWORD_RESET'
        ]);
    }
}
