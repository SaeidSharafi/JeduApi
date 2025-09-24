<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CollaborationRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

final class CollaborationRequestFactory extends Factory
{
    protected $model = CollaborationRequest::class;

    public function definition(): array
    {
        return [
            'full_name'  => $this->faker->name(),
            'phone'      => $this->faker->mobile(),
            'email'      => $this->faker->unique()->safeEmail(),
            'message'    => $this->faker->persianText(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
