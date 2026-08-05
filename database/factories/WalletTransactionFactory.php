<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Models\Order;
use App\Models\Staff;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

final class WalletTransactionFactory extends Factory
{
    protected $model = WalletTransaction::class;

    public function definition(): array
    {
        $amount = $this->faker->numberBetween(-100000, 100000); // -1000 to 1000 IRR

        return [
            'wallet_id' => function (array $attributes) {
                // Create a user and use their auto-created wallet
                $user = User::factory()->create();

                return $user->wallet->id;
            },
            'user_id' => function (array $attributes) {
                // Get the user_id from the wallet
                return Wallet::find($attributes['wallet_id'])->user_id;
            },
            'type'               => $this->faker->randomElement(TransactionTypeEnum::getAllValues()),
            'amount'             => $amount,
            'balance_after'      => $this->faker->numberBetween(0, 1000000),
            'gift_balance_after' => $this->faker->numberBetween(0, 500000),
            'source_type'        => $this->faker->randomElement(TransactionSourceEnum::getAllValues()),
            'source_id'          => $this->faker->optional()->numberBetween(1, 1000),
            'description'        => $this->faker->optional()->sentence(),
            'metadata'           => $this->faker->optional()->randomElement([
                null,
                ['gateway'        => 'test', 'transaction_id' => $this->faker->uuid],
                ['admin_note'     => $this->faker->sentence],
                ['promotion_code' => $this->faker->word],
            ]),
            'expires_at'      => $this->faker->optional()->dateTimeBetween('now', '+1 year'),
            'idempotency_key' => $this->faker->optional()->regexify('[A-Za-z0-9\:\-_]{20,50}'),
            'created_by'      => $this->faker->optional()->randomElement([null, Staff::factory()]),
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now(),
        ];
    }

    public function deposit(?int $amount = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type'        => TransactionTypeEnum::DEPOSIT,
            'amount'      => $amount ?? $this->faker->numberBetween(1000, 100000),
            'source_type' => TransactionSourceEnum::STAFF,
            'source_id'   => Staff::factory(),
        ]);
    }

    public function withdrawal(?int $amount = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type'        => TransactionTypeEnum::WITHDRAWAL,
            'amount'      => -abs($amount ?? $this->faker->numberBetween(1000, 100000)),
            'source_type' => TransactionSourceEnum::STAFF,
            'source_id'   => Staff::factory(),
        ]);
    }

    public function payment(?int $amount = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type'        => TransactionTypeEnum::PAYMENT,
            'amount'      => -abs($amount ?? $this->faker->numberBetween(1000, 100000)),
            'source_type' => TransactionSourceEnum::ORDER,
            'source_id'   => Order::factory(),
        ]);
    }

    public function refund(?int $amount = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type'        => TransactionTypeEnum::REFUND,
            'amount'      => $amount ?? $this->faker->numberBetween(1000, 100000),
            'source_type' => TransactionSourceEnum::ORDER,
            'source_id'   => Order::factory(),
        ]);
    }

    public function gift(?int $amount = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type'        => TransactionTypeEnum::GIFT,
            'amount'      => $amount ?? $this->faker->numberBetween(1000, 50000),
            'source_type' => TransactionSourceEnum::PROMOTION,
            'expires_at'  => $this->faker->dateTimeBetween('+1 month', '+1 year'),
        ]);
    }

    public function bonus(?int $amount = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type'        => TransactionTypeEnum::BONUS,
            'amount'      => $amount ?? $this->faker->numberBetween(1000, 50000),
            'source_type' => TransactionSourceEnum::PROMOTION,
        ]);
    }

    public function forWallet(Wallet $wallet): static
    {
        return $this->state(fn (array $attributes) => [
            'wallet_id' => $wallet->id,
            'user_id'   => $wallet->user_id,
        ]);
    }

    public function withMetadata(array $metadata): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => $metadata,
        ]);
    }

    public function withExpiry(DateTimeImmutable $expiryDate): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $expiryDate,
        ]);
    }
}
