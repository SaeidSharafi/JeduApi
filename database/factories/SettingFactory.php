<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Setting>
 */
class SettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->word,
            'value' => ['test_value' => $this->faker->sentence],
            'type' => 'json',
            'group' => $this->faker->word,
        ];
    }

    /**
     * Create a contact_info setting.
     */
    public function contactInfo(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'key' => 'contact_info',
                'value' => [
                    'addresses' => [
                        [
                            'name' => 'Test Office',
                            'address' => 'Test Address 123',
                            'location_url' => 'https://maps.example.com/?q=35.6892,51.3890',
                            'phone' => '123-456-7890',
                        ],
                    ],
                    'working_hours' => 'Monday to Friday, 9am to 5pm',
                    'support_email' => 'test@example.com',
                    'social_media_links' => [
                        [
                            'platform' => 'twitter',
                            'link' => 'https://twitter.com/test',
                        ],
                    ],
                ],
                'type' => 'json',
                'group' => 'contact',
            ];
        });
    }
}
