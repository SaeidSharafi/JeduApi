<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Wallet\CampaignTypeEnum;
use App\Models\Staff;
use App\Models\WalletCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

final class WalletCampaignFactory extends Factory
{
    protected $model = WalletCampaign::class;

    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-1 month', '+1 week');
        $endDate = $this->faker->dateTimeBetween($startDate, '+3 months');

        return [
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->optional()->paragraph(),
            'type' => $this->faker->randomElement(CampaignTypeEnum::cases())->value,
            'amount' => $this->faker->numberBetween(10000, 500000), // 100 to 5000 IRR
            'is_active' => $this->faker->boolean(80), // 80% chance of being active
            'usage_limit_total' => $this->faker->optional(0.7)->numberBetween(10, 10000),
            'usage_limit_per_user' => $this->faker->optional(0.8)->numberBetween(1, 10),
            'total_usage_count' => 0,
            'starts_at' => Carbon::instance($startDate),
            'ends_at' => Carbon::instance($endDate),
            'metadata' => $this->faker->optional(0.3)->passthrough([
                'terms_conditions' => $this->faker->paragraph(),
                'target_audience' => $this->faker->words(3),
                'promotional_code' => $this->faker->optional()->regexify('[A-Z0-9]{8}'),
            ]),
            'created_by' => Staff::factory(),
        ];
    }

    /**
     * Create an active campaign that's currently running
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'starts_at' => Carbon::now()->subDays(7),
            'ends_at' => Carbon::now()->addMonth(),
        ]);
    }

    /**
     * Create an inactive campaign
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create an expired campaign
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => Carbon::now()->subMonth(),
            'ends_at' => Carbon::now()->subWeek(),
        ]);
    }

    /**
     * Create a campaign that hasn't started yet
     */
    public function notStarted(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => Carbon::now()->addWeek(),
            'ends_at' => Carbon::now()->addMonth(),
        ]);
    }

    /**
     * Create a campaign with usage limits
     */
    public function withLimits(int $totalLimit = 100, int $perUserLimit = 5): static
    {
        return $this->state(fn (array $attributes) => [
            'usage_limit_total' => $totalLimit,
            'usage_limit_per_user' => $perUserLimit,
        ]);
    }

    /**
     * Create a campaign that has reached its total usage limit
     */
    public function exhausted(): static
    {
        return $this->state(function (array $attributes) {
            $limit = $attributes['usage_limit_total'] ?? 100;
            return [
                'usage_limit_total' => $limit,
                'total_usage_count' => $limit,
            ];
        });
    }

    /**
     * Create a campaign of a specific type
     */
    public function ofType(CampaignTypeEnum $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type->value,
        ]);
    }

    /**
     * Create a referral bonus campaign
     */
    public function referralBonus(): static
    {
        return $this->ofType(CampaignTypeEnum::REFERRAL_BONUS)
            ->state(fn (array $attributes) => [
                'name' => 'Referral Bonus Campaign',
                'amount' => 50000, // 500 IRR
                'metadata' => [
                    'bonus_type' => 'referral',
                    'trigger_event' => 'user_referred',
                ],
            ]);
    }

    /**
     * Create a birthday gift campaign
     */
    public function birthdayGift(): static
    {
        return $this->ofType(CampaignTypeEnum::BIRTHDAY_GIFT)
            ->state(fn (array $attributes) => [
                'name' => 'Birthday Gift Campaign',
                'amount' => 25000, // 250 IRR
                'usage_limit_per_user' => 1,
                'metadata' => [
                    'bonus_type' => 'birthday',
                    'annual_bonus' => true,
                ],
            ]);
    }

    /**
     * Create a welcome gift campaign
     */
    public function welcomeGift(): static
    {
        return $this->ofType(CampaignTypeEnum::WELCOME_GIFT)
            ->state(fn (array $attributes) => [
                'name' => 'Welcome Gift Campaign',
                'amount' => 100000, // 1000 IRR
                'metadata' => [
                    'gift_type' => 'promotional',
                    'non_withdrawable' => true,
                ],
            ]);
    }
}
