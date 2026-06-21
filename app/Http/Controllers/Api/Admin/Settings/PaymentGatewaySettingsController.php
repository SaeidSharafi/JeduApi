<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Settings;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\MediaData;
use App\Data\Admin\Settings\Gateway\GatewaySettingCreateData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\Request;

/**
 * @group Admin - Settings Management
 *
 * @authenticated
 */
final class PaymentGatewaySettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settingsService) {}

    /**
     * List all payment gateway settings.
     *
     * Returns all payment gateways with their current configuration and the field schema
     * needed for the frontend to render the settings form.
     *
     * Each item in `gateways` contains:
     * - `key` (string): Gateway identifier. One of: `mellat`, `wallet`, `bank_transfer`, `digipay`.
     * - `schema` (object): Grouped field definitions. Groups: `general`, `credentials`, `testing`.
     * - `settings` (object): Current stored values. Sensitive fields are returned as `••••••`.
     *
     * Each field in `schema` has:
     * - `key` (string): Field key matching the request body field name.
     * - `type` (string): One of `text`, `password`, `textarea`, `url`, `boolean`, `media`.
     * - `label` (string): Localized human-readable label.
     * - `required` (boolean): Whether the field is required.
     * - `sensitive` (boolean, optional): Whether the stored value is masked on read-back.
     *
     * @responseFile 200 resources/responses/admin/settings/payment-gateways/index.json
     * @responseFile 403 resources/responses/403.json
     */
    public function index(): ApiResponseInterface
    {
        $gateways = collect(PaymentMethodEnum::cases())
            ->filter(fn (PaymentMethodEnum $gateway) => $gateway->settingKey() !== null)
            ->map(function (PaymentMethodEnum $gateway) {
                $stored = $this->settingsService->get($gateway->settingKey(), []);

                if (isset($stored['icon']) && is_array($stored['icon'])) {
                    $stored['icon'] = MediaData::from($stored['icon']);
                }

                return [
                    'key'      => $gateway->value,
                    'label'    => $gateway->translate(),
                    'schema'   => GatewaySettingCreateData::schemaForGateway($gateway),
                    'settings' => $stored,
                ];
            });

        return response()->success($gateways->values());
    }

    /**
     *
     * Get gateway setting
     *
     * @urlParam gateway string required The gateway key. Enum: `mellat`, `wallet`, `bank_transfer`, `digipay`. Example: mellat
     * @responseFile 200 resources/responses/admin/settings/payment-gateways/show.json
     * @responseFile 403 resources/responses/403.json
     */
    public function show(PaymentMethodEnum $gateway): ApiResponseInterface
    {
        $gatewayData = $this->settingsService->get($gateway->settingKey());

        return response()->success($gatewayData);
    }

    /**
     * Update payment gateway settings.
     *
     * Updates configuration for a specific payment gateway. Sensitive fields are encrypted at rest.
     * To keep an existing sensitive value unchanged, omit the field or send `null`.
     *
     * <aside class="warning">The request body varies by gateway. All gateways share the common fields below, with additional fields per gateway.</aside>
     *
     * ### Common fields (all gateways):
     * - `enabled` (boolean, require): Whether the gateway is active and available for use.
     * - `shop_enabled` (boolean, required): Whether the gateway is offered to customers at checkout.
     * - `label` (string, required): Display name shown to customers.
     * - `description` (string, optional): Description shown at checkout.
     * - `icon` (integer, optional): Media ID of the gateway icon image.
     * - `ims_bank_account_number` (string, required): IMS settlement account number. **Encrypted at rest.**
     *
     * ### Gateway: `mellat`
     * - `terminal_id` (string, required): Terminal ID provided by Mellat bank.
     * - `username` (string, required): Mellat merchant username.
     * - `password` (string, required on first save / nullable on update): Mellat merchant password. **Encrypted at rest.** Omit or send `null` to keep existing.
     * - `test_mode` (boolean, optional): Enable test/sandbox mode. Default: `false`.
     * - `test_server_url` (url, optional): Sandbox WSDL endpoint. Recommended when `test_mode` is `true`.
     * - `test_gateway_url` (url, optional): Sandbox redirect URL. Recommended when `test_mode` is `true`.
     *
     * ### Gateway: `digipay`
     * - `client_id` (string, required): Digipay OAuth client ID.
     * - `client_secret` (string, required on first save / nullable on update): Digipay OAuth client secret. **Encrypted at rest.** Omit or send `null` to keep existing.
     * - `username` (string, required): Mellat merchant username.
     * - `password` (string, required on first save / nullable on update): Mellat merchant password. **Encrypted at rest.** Omit or send `null` to keep existing.
     * - `sandbox_mode` (boolean, optional): Enable Digipay UAT/sandbox mode. Default: `false`.
     *
     * ### Gateway: `wallet`
     * No additional fields beyond common fields.
     *
     * ### Gateway: `bank_transfer`
     * No additional fields beyond common fields.
     *
     * @urlParam gateway string required The gateway key. Enum: `mellat`, `wallet`, `bank_transfer`, `digipay`. Example: mellat
     *
     * @responseFile 200 resources/responses/admin/settings/payment-gateways/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 422 resources/responses/422.json
     */
    public function update(GatewaySettingCreateData $request, PaymentMethodEnum $gateway): ApiResponseInterface
    {
        $this->settingsService->set($gateway->settingKey(), $request->toArray());
        $gatewayData = $this->settingsService->get($gateway->settingKey());

        return response()->success($gatewayData);
    }
}
