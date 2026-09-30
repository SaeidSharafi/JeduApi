<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Content\PublicationStatusEnum;
use App\Models\LearningPath;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningPath>
 */
final class LearningPathFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title'                    => $this->faker->sentence(3),
            'slug'                     => $this->faker->unique()->slug(),
            'description'              => $this->faker->paragraph(),
            'introduction_title'       => $this->faker->sentence(3),
            'introduction_description' => $this->faker->paragraph(),
            'conclusion_title'         => $this->faker->sentence(3),
            'conclusion_description'   => $this->faker->paragraph(),
            'meta_title'               => $this->faker->sentence(4),
            'meta_description'         => $this->faker->sentence(12),
            'meta_keywords'            => implode(', ', $this->faker->words(3)),
            'display_order'            => 0,
            'status'                   => PublicationStatusEnum::DRAFT,
        ];
    }
}
