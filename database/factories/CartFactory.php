<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cart>
 */
final class CartFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'     => null, // Optional, can be set when creating
            'guest_token' => null, // Optional, can be set when creating
        ];
    }

    /**
     * Indicate that the cart belongs to an authenticated user.
     */
    public function forUser(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id'     => $user?->id ?? User::factory(),
            'guest_token' => null,
        ]);
    }

    /**
     * Indicate that the cart belongs to a guest user.
     */
    public function forGuest(?string $token = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id'     => null,
            'guest_token' => $token ?? Str::uuid()->toString(),
        ]);
    }
}
