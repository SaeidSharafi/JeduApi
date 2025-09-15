<?php

namespace Database\Factories;

use App\Enums\AdviceRequestStatusEnum;
use App\Models\AdviceRequest;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class AdviceRequestFactory extends Factory
{
    protected $model = AdviceRequest::class;

    public function definition(): array
    {
        return [
            'phone'         => $this->faker->mobile(),
            'status'        => $this->faker->randomElement(AdviceRequestStatusEnum::cases()),
            'note'          => $this->faker->word(),
            'handled_by_id' => Staff::factory(),
            'created_at'    => Carbon::now(),
            'updated_at'    => Carbon::now(),
        ];
    }
}
