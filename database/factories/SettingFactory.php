<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Setting>
 */
final class SettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key'   => $this->faker->unique()->word,
            'value' => ['test_value' => $this->faker->sentence],
            'type'  => 'json',
            'group' => $this->faker->word,
        ];
    }

    /**
     * Create an IMS integration setting with a plaintext api_key.
     */
    public function ims(): static
    {
        return $this->state(fn (array $attributes) => [
            'key'   => 'ims',
            'value' => [
                'enabled'  => false,
                'base_url' => 'https://ims.example.com',
                'api_key'  => 'super-secret-ims-key',
            ],
            'type'  => 'json',
            'group' => 'integrations',
        ]);
    }

    /**
     * Create a Moodle integration setting with plaintext secrets.
     */
    public function moodle(): static
    {
        return $this->state(fn (array $attributes) => [
            'key'   => 'moodle',
            'value' => [
                'enabled'            => false,
                'base_url'           => 'https://moodle.example.com',
                'token'              => 'moodle-token-secret',
                'auth_userkey_token' => 'moodle-userkey-secret',
            ],
            'type'  => 'json',
            'group' => 'integrations',
        ]);
    }

    /**
     * Create a BigBlueButton integration setting with plaintext secrets.
     */
    public function bigBlueButton(): static
    {
        return $this->state(fn (array $attributes) => [
            'key'   => 'big_blue_button',
            'value' => [
                'enabled'                    => false,
                'base_url'                   => 'https://bbb.example.com',
                'secret'                     => 'bbb-shared-secret',
                'default_attendee_password'  => 'attendee-pass',
                'default_moderator_password' => 'moderator-pass',
            ],
            'type'  => 'json',
            'group' => 'integrations',
        ]);
    }

    /**
     * Create a SpotPlayer integration setting with a plaintext api_key.
     */
    public function spotPlayer(): static
    {
        return $this->state(fn (array $attributes) => [
            'key'   => 'spot_player',
            'value' => [
                'enabled'  => false,
                'endpoint' => 'https://panel.spotplayer.ir/license/edit/',
                'api_key'  => 'spotplayer-api-key-secret',
                'sandbox'  => false,
            ],
            'type'  => 'json',
            'group' => 'integrations',
        ]);
    }

    /**
     * Create an SMS IPPanel gateway setting with an encrypted api_key.
     */
    public function smsIppanel(): static
    {
        return $this->state(fn (array $attributes) => [
            'key'   => 'sms.ippanel',
            'value' => [
                'enabled' => true,
                'label'   => 'IPPanel',
                'from'    => '1000',
                'api_key' => Crypt::encryptString('sms-ippanel-api-key'),
                'sandbox' => false,
            ],
            'type'  => 'json',
            'group' => 'sms',
        ]);
    }

    /**
     * Create an SMS IPPanel setting that differs from the config defaults.
     *
     * Carries the runtime `sand_box` key as well, so the read path can be
     * asserted to drop stored keys the contract does not declare.
     */
    public function smsIppanelSecondary(): static
    {
        return $this->state(fn (array $attributes) => [
            'key'   => 'sms.ippanel',
            'value' => [
                'enabled'  => false,
                'label'    => 'IPPanel secondary',
                'from'     => '2000',
                'api_key'  => Crypt::encryptString('sms-ippanel-api-key'),
                'sandbox'  => true,
                'sand_box' => true,
            ],
            'type'  => 'json',
            'group' => 'sms',
        ]);
    }

    /**
     * Create a contact_info setting.
     */
    public function contactInfo(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'key'   => 'contact_info',
                'value' => [
                    'addresses' => [
                        [
                            'name'         => 'Test Office',
                            'address'      => 'Test Address 123',
                            'location_url' => 'https://maps.example.com/?q=35.6892,51.3890',
                            'phone'        => '123-456-7890',
                        ],
                    ],
                    'working_hours'      => 'Monday to Friday, 9am to 5pm',
                    'support_email'      => 'test@example.com',
                    'social_media_links' => [
                        [
                            'platform' => 'twitter',
                            'link'     => 'https://twitter.com/test',
                        ],
                    ],
                ],
                'type'  => 'json',
                'group' => 'contact',
            ];
        });
    }
}
