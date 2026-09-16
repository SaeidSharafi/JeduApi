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
     * Create an IMS setting that differs from the config defaults.
     *
     * Carries a legacy `create_studets` flag the contract no longer declares, so
     * the read path can be asserted to drop stored keys the schema does not own.
     */
    public function imsSecondary(): static
    {
        return $this->state(fn (array $attributes) => [
            'key'   => 'ims',
            'value' => [
                'enabled'        => true,
                'base_url'       => 'https://stored-ims.example.com',
                'api_key'        => 'stored-ims-key',
                'timeout'        => 30,
                'create_studets' => true,
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
     * Create a Skyroom integration setting with a plaintext api_key.
     *
     * Carries the legacy `secret` credential the panel does not expose, so a
     * save can be asserted to preserve it.
     */
    public function skyroom(): static
    {
        return $this->state(fn (array $attributes) => [
            'key'   => 'skyroom',
            'value' => [
                'enabled'  => true,
                'base_url' => 'https://skyroom.example.com',
                'api_key'  => 'skyroom-api-key',
                'secret'   => 'skyroom-secret',
            ],
            'type'  => 'json',
            'group' => 'integrations',
        ]);
    }

    /**
     * Create a Niliroom integration setting with a plaintext api_token.
     */
    public function niliroom(): static
    {
        return $this->state(fn (array $attributes) => [
            'key'   => 'niliroom',
            'value' => [
                'enabled'   => true,
                'base_url'  => 'https://niliroom.example.com',
                'api_token' => 'niliroom-api-token',
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
     * Create an SMS notification options setting that differs from the config defaults.
     *
     * `otp` is stored disabled with its previous pattern, so the update path can
     * be asserted to keep the code when the option is disabled with an empty
     * value. `order_paid` carries a stored key the contract does not declare.
     */
    public function smsNotifications(): static
    {
        return $this->state(fn (array $attributes) => [
            'key'   => 'sms_notifications',
            'value' => [
                'otp' => [
                    'enabled'      => false,
                    'pattern_code' => 'stored-otp-pattern',
                ],
                'order_paid' => [
                    'enabled'      => true,
                    'pattern_code' => '',
                    'log_type'     => 'ORDER',
                ],
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
