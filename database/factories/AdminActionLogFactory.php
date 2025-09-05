<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AdminActionLog;
use App\Models\Staff;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminActionLog>
 */
class AdminActionLogFactory extends Factory
{
    protected $model = AdminActionLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admin_id' => Staff::factory(),
            'action_type' => $this->faker->randomElement(['create', 'update', 'delete', 'view']),
            'resource_type' => $this->faker->randomElement([
                'App\\Models\\User',
                'App\\Models\\Wallet',
                'App\\Models\\Category',
                'App\\Models\\Course',
            ]),
            'resource_id' => $this->faker->numberBetween(1, 1000),
            'route_name' => $this->faker->randomElement([
                'admin.users.index',
                'admin.wallets.deposit',
                'admin.categories.store',
                'admin.courses.update',
            ]),
            'http_method' => $this->faker->randomElement(['GET', 'POST', 'PUT', 'DELETE']),
            'request_data' => [
                'param1' => $this->faker->word,
                'param2' => $this->faker->numberBetween(1, 100),
            ],
            'response_status' => $this->faker->randomElement([200, 201, 204, 404, 422, 500]),
            'ip_address' => $this->faker->ipv4,
            'user_agent' => $this->faker->userAgent,
            'session_id' => $this->faker->uuid,
            'risk_level' => $this->faker->randomElement(['low', 'medium', 'high']),
            'metadata' => [
                'execution_time' => $this->faker->numberBetween(10, 1000),
                'memory_usage' => $this->faker->numberBetween(1024, 10240),
            ],
        ];
    }

    /**
     * Create a high-risk audit log.
     */
    public function highRisk(): static
    {
        return $this->state(fn (array $attributes) => [
            'risk_level' => 'high',
            'action_type' => $this->faker->randomElement(['delete', 'update']),
            'resource_type' => $this->faker->randomElement([
                'App\\Models\\Wallet',
                'App\\Models\\Staff',
            ]),
        ]);
    }

    /**
     * Create a wallet-related audit log.
     */
    public function walletAction(): static
    {
        return $this->state(fn (array $attributes) => [
            'resource_type' => 'App\\Models\\Wallet',
            'route_name' => $this->faker->randomElement([
                'admin.wallets.deposit',
                'admin.wallets.withdraw',
                'admin.wallets.adjust',
            ]),
            'action_type' => $this->faker->randomElement(['deposit', 'withdrawal', 'adjustment']),
        ]);
    }

    /**
     * Create a successful action log.
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'response_status' => $this->faker->randomElement([200, 201, 204]),
        ]);
    }

    /**
     * Create a failed action log.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'response_status' => $this->faker->randomElement([400, 404, 422, 500]),
        ]);
    }
}
