<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

final class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id'    => Order::factory(),
            'customer_id' => User::factory(),
            'purpose'     => PaymentPurposeEnum::ORDER,
            'amount'      => $this->faker->numberBetween(1000, 100000), // Amount in cents
            'method'      => $this->faker->randomElement(PaymentMethodEnum::getAllValues()),
            'status'      => $this->faker->randomElement(PaymentStatusEnum::getAllValues()),
            'admin_notes' => $this->faker->optional()->text(200),
            'created_by'  => Staff::factory(),
            'created_at'  => Carbon::now(),
            'updated_at'  => Carbon::now(),
        ];
    }

    public function topup(): static
    {
        return $this->state([
            'order_id' => null,
            'purpose'  => PaymentPurposeEnum::WALLET_TOPUP,
        ]);
    }
}
