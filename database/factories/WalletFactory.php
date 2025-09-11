<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Wallet\WalletStatusEnum;
use App\Models\Staff;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

final class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    public function definition(): array
    {
        // Note: When creating a wallet through the factory, you should manually provide user_id
        // to avoid conflicts with automatic wallet creation
        return [
            'user_id'      => null, // Should be provided explicitly
            'balance'      => $this->faker->numberBetween(0, 1000000), // 0 to 10,000 IRR
            'gift_balance' => $this->faker->numberBetween(0, 500000), // 0 to 5,000 IRR
            'status'       => $this->faker->randomElement(WalletStatusEnum::getAllValues()),
            'created_by'   => $this->faker->optional()->randomElement([null, Staff::factory()]),
            'created_at'   => Carbon::now(),
            'updated_at'   => Carbon::now(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WalletStatusEnum::ACTIVE,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WalletStatusEnum::SUSPENDED,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WalletStatusEnum::CLOSED,
        ]);
    }

    public function withBalance(int $balance): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => $balance,
        ]);
    }

    public function withGiftBalance(int $giftBalance): static
    {
        return $this->state(fn (array $attributes) => [
            'gift_balance' => $giftBalance,
        ]);
    }

    public function empty(): static
    {
        return $this->state(fn (array $attributes) => [
            'balance'      => 0,
            'gift_balance' => 0,
        ]);
    }
}
