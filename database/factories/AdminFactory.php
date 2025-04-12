<?php

namespace Database\Factories;

use App\Models\Admin;
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
            'email_verified_at' => now(),
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
            $admin->otp()->create([
                'identifier' => $admin->email,
                'type' => 'email',
                'code' => $code,
                'expires_at' => now()->addMinutes(5),
                'purpose' => 'LOGIN'
            ]);
        });
    }
}
