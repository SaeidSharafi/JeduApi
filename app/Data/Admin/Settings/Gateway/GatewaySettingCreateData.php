<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings\Gateway;

use App\Enums\Payment\PaymentMethodEnum;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class GatewaySettingCreateData extends Data
{
    public function __construct(
        public bool $enabled,
        public bool $shop_enabled,
        public string $label,
        public ?string $description,
        public ?int $icon,
        public ?string $ims_bank_account_number,
        public mixed $config = null,
    ) {
        $gatewayParam = request()->route('gateway');
        $gateway      = $gatewayParam instanceof PaymentMethodEnum
            ? $gatewayParam
            : PaymentMethodEnum::tryFrom((string) $gatewayParam);

        if ($gateway && is_array($config)) {
            $configClass = $gateway->settingDataClass();
            if ($configClass && is_subclass_of($configClass, Data::class)) {
                $this->config = $configClass::from($config);
            }
        }
    }

    /**
     * Centralized common fields schema definition.
     */
    public static function commonSchema(): array
    {
        return [
            [
                'key'      => 'label',
                'type'     => 'text',
                'label'    => __('payment_gateways.fields.label'),
                'required' => true,
            ],
            [
                'key'      => 'description',
                'type'     => 'textarea',
                'label'    => __('payment_gateways.fields.description'),
                'required' => false,
            ],
            [
                'key'      => 'icon',
                'type'     => 'media',
                'label'    => __('payment_gateways.fields.icon'),
                'required' => false,
            ],
            [
                'key'      => 'ims_bank_account_number',
                'type'     => 'text',
                'label'    => __('payment_gateways.fields.ims_bank_account_number'),
                'required' => true,
            ],
        ];
    }

    /**
     * Dynamically construct the correct schema for the index endpoint.
     */
    public static function schemaForGateway(PaymentMethodEnum $gateway): array
    {
        $schema = [
            'general' => self::commonSchema(),
        ];

        $configClass = $gateway->settingDataClass();
        if ($configClass && is_subclass_of($configClass, Data::class) && method_exists($configClass, 'schema')) {
            $configSchema = $configClass::schema();

            // Helper function to prepend 'config.' to field keys
            $prefixKeys = function (array $fields) {
                return array_map(function (array $field) {
                    $field['key'] = "config.{$field['key']}";

                    return $field;
                }, $fields);
            };

            if (array_is_list($configSchema)) {
                $schema['config'] = $prefixKeys($configSchema);
            } else {
                foreach ($configSchema as $group => $fields) {
                    $schema[$group] = $prefixKeys($fields);
                }
            }
        }

        return $schema;
    }

    public static function rules(?ValidationContext $context = null): array
    {
        $rules = [
            'enabled'                 => ['required', 'boolean'],
            'shop_enabled'            => ['required', 'boolean'],
            'label'                   => ['required', 'string'],
            'description'             => ['nullable', 'string'],
            'icon'                    => ['nullable', 'integer'],
            'ims_bank_account_number' => ['nullable', 'string'],
            'config'                  => ['nullable', 'array'],
        ];

        $gatewayParam = request()->route('gateway');
        $gateway      = $gatewayParam instanceof PaymentMethodEnum
            ? $gatewayParam
            : PaymentMethodEnum::tryFrom((string) $gatewayParam);

        if ($gateway) {
            $configClass = $gateway->settingDataClass();

            if ($configClass && is_subclass_of($configClass, Data::class)) {
                $rules['config'] = ['required', 'array'];

                $configPayload = $context ? data_get($context->payload, 'config', []) : [];
                $configRules   = $configClass::getValidationRules($configPayload);

                foreach ($configRules as $key => $rule) {
                    $rules["config.{$key}"] = $rule;
                }
            } else {
                $rules['config'] = ['nullable', 'prohibited'];
            }
        }

        return $rules;
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'enabled' => [
                'description' => 'Whether the gateway is active and available for use.',
                'example'     => true,
            ],
            'shop_enabled' => [
                'description' => 'Whether the gateway is offered to customers at checkout.',
                'example'     => true,
            ],
            'label' => [
                'description' => 'Display name shown to customers.',
                'example'     => 'Mellat Bank',
            ],
            'description' => [
                'description' => 'Description shown at checkout.',
                'example'     => 'Pay securely with Mellat Bank.',
            ],
            'icon' => [
                'description' => 'Media ID of the gateway icon image.',
                'example'     => 1,
            ],
            'ims_bank_account_number' => [
                'description' => 'IMS settlement account number. Encrypted at rest.',
                'example'     => '1234567890',
            ],
            'config' => [
                'description' => 'Provider-specific settings, keyed by the route gateway. For example `mellat` accepts `terminal_id`, `username`, `password` and `test_mode`; `digipay` accepts `client_id`, `client_secret`, `username`, `password` and `sandbox_mode`.',
                'example'     => ['terminal_id' => '123456', 'username' => 'merchant', 'password' => null, 'test_mode' => false],
            ],
        ];
    }
}
