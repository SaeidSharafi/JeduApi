<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Payment\PaymentTransactionStatusEnum;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentTransaction>
 */
final class PaymentTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $initiatedAt = $this->faker->dateTimeBetween('-1 month', 'now');
        $status      = $this->faker->randomElement(PaymentTransactionStatusEnum::cases());
        $completedAt = $status !== PaymentTransactionStatusEnum::INITIATED
            ? $this->faker->dateTimeBetween($initiatedAt, 'now')
            : null;

        return [
            'payment_id'            => Payment::factory(),
            'transaction_reference' => (string) $this->faker->unique()->numberBetween(200000001, 299999999),
            'attempt_number'        => $this->faker->numberBetween(1, 5),
            'status'                => $status,
            'gateway_request'       => [
                'amount'   => $this->faker->numberBetween(10000, 1000000),
                'order_id' => $this->faker->numberBetween(1, 1000),
            ],
            'gateway_response' => $status !== PaymentTransactionStatusEnum::INITIATED ? [
                'success'      => $status === PaymentTransactionStatusEnum::COMPLETED,
                'reference_id' => $this->faker->uuid(),
            ] : null,
            'initiated_at'  => $initiatedAt,
            'completed_at'  => $completedAt,
            'error_code'    => $status === PaymentTransactionStatusEnum::FAILED ? 'PAYMENT_FAILED' : null,
            'error_message' => $status === PaymentTransactionStatusEnum::FAILED ? 'Payment failed' : null,
            'ip_address'    => $this->faker->ipv4(),
            'user_agent'    => $this->faker->userAgent(),
        ];
    }

    public function initiated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'        => PaymentTransactionStatusEnum::INITIATED,
            'completed_at'  => null,
            'error_code'    => null,
            'error_message' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'           => PaymentTransactionStatusEnum::COMPLETED,
            'completed_at'     => now(),
            'gateway_response' => [
                'success'      => true,
                'reference_id' => $this->faker->uuid(),
            ],
            'error_code'    => null,
            'error_message' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'           => PaymentTransactionStatusEnum::FAILED,
            'completed_at'     => now(),
            'gateway_response' => [
                'success' => false,
                'error'   => 'Payment gateway error',
            ],
            'error_code'    => 'GATEWAY_ERROR',
            'error_message' => 'Payment was declined by the gateway',
        ]);
    }
}
