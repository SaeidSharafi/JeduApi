<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InboundRequestStatusEnum;
use App\Models\OrganizationTrainingRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<OrganizationTrainingRequest>
 */
final class OrganizationTrainingRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name'             => $this->faker->firstName(),
            'last_name'              => $this->faker->lastName(),
            'phone'                  => $this->faker->mobile(),
            'position'               => $this->faker->jobTitle(),
            'organization_name'      => $this->faker->company(),
            'requested_course_names' => [$this->faker->sentence(3)],
            'notes'                  => $this->faker->optional()->sentence(),
            'status'                 => InboundRequestStatusEnum::PENDING,
            'created_at'             => Carbon::now(),
            'updated_at'             => Carbon::now(),
        ];
    }
}
