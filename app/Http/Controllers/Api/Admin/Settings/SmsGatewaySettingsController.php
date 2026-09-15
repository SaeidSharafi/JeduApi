<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Settings;

use App\Actions\Admin\Settings\Sms\BuildSmsGatewaySettingAction;
use App\Actions\Admin\Settings\Sms\UpdateSmsGatewaySettingAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Settings\Sms\SmsGatewaySettingData;
use App\Enums\Sms\SmsGatewayEnum;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Settings Management
 *
 * @authenticated
 */
final class SmsGatewaySettingsController extends Controller
{
    public function __construct(
        private readonly BuildSmsGatewaySettingAction $buildGatewaySetting,
        private readonly UpdateSmsGatewaySettingAction $updateGatewaySetting,
    ) {}

    /**
     * List SMS gateway settings.
     *
     * Returns every configurable SMS gateway with the field schema the panel
     * renders and the effective settings for it. Values come from the stored
     * setting when one exists and from the SMS configuration file otherwise, so
     * a never-saved gateway still returns a fully populated settings object.
     *
     * Each item contains:
     * - `key` (string): Gateway identifier. Currently `ippanel`.
     * - `label` (string): Localized display label.
     * - `schema` (object): Grouped field definitions. Groups: `general`, `credentials`, `testing`.
     * - `settings` (object): Effective values. `api_key` is always masked.
     *
     * @responseFile 200 resources/responses/admin/settings/sms-gateways/index.json
     * @responseFile 403 resources/responses/403.json
     */
    public function index(): ApiResponseInterface
    {
        Gate::authorize('viewAny', Setting::class);

        $gateways = collect(SmsGatewayEnum::cases())
            ->map(fn (SmsGatewayEnum $gateway): array => $this->buildGatewaySetting->handle($gateway))
            ->values();

        return apiResponse()->success($gateways);
    }

    /**
     * Get an SMS gateway setting.
     *
     * Returns a single gateway shaped exactly like an element of the list
     * `data`, so a detail page can be opened by deep link without first
     * fetching the list.
     *
     * @urlParam gateway string required The gateway key. Enum: `ippanel`. Example: ippanel
     *
     * @responseFile 200 resources/responses/admin/settings/sms-gateways/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     */
    public function show(SmsGatewayEnum $gateway): ApiResponseInterface
    {
        Gate::authorize('viewAny', Setting::class);

        return apiResponse()->success($this->buildGatewaySetting->handle($gateway));
    }

    /**
     * Update an SMS gateway setting.
     *
     * The body is flat and uses the same keys as the item's `schema`. To keep
     * the stored API key, omit `api_key` or send `null` (sending the masked
     * placeholder back also keeps it); an explicit empty string clears it.
     * Turning the gateway on without a sender number or a stored key is
     * rejected with a `422` naming the offending field.
     *
     * @urlParam gateway string required The gateway key. Enum: `ippanel`. Example: ippanel
     *
     * @responseFile 200 resources/responses/admin/settings/sms-gateways/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     * @responseFile 422 resources/responses/422.json
     */
    public function update(SmsGatewaySettingData $request, SmsGatewayEnum $gateway): ApiResponseInterface
    {
        Gate::authorize('update', Setting::class);

        return apiResponse()->success($this->updateGatewaySetting->handle($gateway, $request));
    }
}
