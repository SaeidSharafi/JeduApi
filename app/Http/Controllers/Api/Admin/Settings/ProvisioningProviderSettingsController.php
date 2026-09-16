<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Settings;

use App\Actions\Admin\Settings\Provisioning\BuildProvisioningProviderSettingAction;
use App\Actions\Admin\Settings\Provisioning\UpdateProvisioningProviderSettingAction;
use App\Contracts\ApiResponseInterface;
use App\Enums\Provisioning\ProvisioningProviderSettingsEnum;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Settings Management
 *
 * @authenticated
 */
final class ProvisioningProviderSettingsController extends Controller
{
    public function __construct(
        private readonly BuildProvisioningProviderSettingAction $buildProviderSetting,
        private readonly UpdateProvisioningProviderSettingAction $updateProviderSetting,
    ) {}

    /**
     * List provisioning provider settings.
     *
     * Returns every configurable provisioning provider with the field schema the
     * panel renders, the effective settings for it, and the computed state.
     * Values come from the stored setting when one exists and from the
     * provisioning configuration file otherwise, so a never-saved provider still
     * returns a complete settings object.
     *
     * Each item contains:
     * - `key` (string): Provider identifier. One of: `ims`, `moodle`, `spotplayer`, `skyroom`, `niliroom`.
     * - `label` (string): Localized display label.
     * - `state` (object): `enabled`, `configured` (every required field is filled) and `ready` (`enabled && configured`).
     * - `schema` (object): Grouped field definitions. Groups: `general` (the switch and the behaviour defaults) and `connection` (service URL and credentials).
     * - `settings` (object): Effective values. Sensitive fields are always masked.
     *
     * @responseFile 200 resources/responses/admin/settings/provisioning-providers/index.json
     * @responseFile 403 resources/responses/403.json
     */
    public function index(): ApiResponseInterface
    {
        Gate::authorize('viewAny', Setting::class);

        $providers = collect(ProvisioningProviderSettingsEnum::cases())
            ->map(fn (ProvisioningProviderSettingsEnum $provider): array => $this->buildProviderSetting->handle($provider))
            ->values();

        return apiResponse()->success($providers);
    }

    /**
     * Get a provisioning provider setting.
     *
     * Returns a single provider shaped exactly like an element of the list
     * `data`, so a detail page can be opened by deep link without first fetching
     * the list. Moodle Quiz is not offered at all, so its key and any unknown
     * key are `404`.
     *
     * @urlParam provider string required The provider key. Enum: `ims`, `moodle`, `spotplayer`, `skyroom`, `niliroom`. Example: ims
     *
     * @responseFile 200 resources/responses/admin/settings/provisioning-providers/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     */
    public function show(ProvisioningProviderSettingsEnum $provider): ApiResponseInterface
    {
        Gate::authorize('viewAny', Setting::class);

        return apiResponse()->success($this->buildProviderSetting->handle($provider));
    }

    /**
     * Update a provisioning provider setting.
     *
     * The body is flat and uses the same keys as the item's `schema`. Because it
     * is resolved from the route's provider at runtime, no single Data class
     * owns the request, so its parameters are documented here for Scribe. Every
     * provider accepts `enabled`; `timeout` exists only on `ims`, `moodle` and
     * `spotplayer`, and the remaining fields depend on the provider. Non-secret
     * fields follow full-replace semantics: send the whole object from the form,
     * and any field that is omitted or cleared falls back to its configured
     * default. Sensitive fields — `api_key`, `api_token`, `token` and
     * `auth_userkey_token` — keep their stored value when omitted or `null`
     * (sending the masked placeholder back also keeps it), and are cleared by an
     * explicit empty string.
     *
     * `enabled` is always required. A provider may be saved disabled with empty
     * connection fields so it can be staged, but turning it on without every
     * required field is rejected with a `422` naming the offending fields.
     *
     * @urlParam provider string required The provider key. Enum: `ims`, `moodle`, `spotplayer`, `skyroom`, `niliroom`. Example: ims
     *
     * @bodyParam enabled boolean required Whether the provider may be used for provisioning. Example: true
     * @bodyParam timeout integer nullable Request timeout in seconds. Used by `ims`, `moodle` and `spotplayer`. Example: 15
     * @bodyParam base_url string nullable Service URL. Required while the provider is enabled by `ims`, `moodle` and `niliroom`, and optional for `skyroom`, which falls back to the shipped Skyroom API endpoint. Example: https://ims.example.ir
     * @bodyParam endpoint string nullable Service URL, named `endpoint` rather than `base_url`. Required while the provider is enabled. Used by `spotplayer`. Example: https://panel.spotplayer.ir/license/edit/
     * @bodyParam api_key string nullable API key. Required while the provider is enabled. Used by `ims`, `spotplayer` and `skyroom`. Example: my-api-key
     * @bodyParam api_token string nullable Niliroom API token. Required while the provider is enabled. Used by `niliroom`. Example: my-api-token
     * @bodyParam token string nullable Moodle service token, used for the administrative calls (users, courses, enrolments). Required while the provider is enabled. Used by `moodle`. Example: my-service-token
     * @bodyParam auth_userkey_token string nullable Moodle login token, used by `auth_userkey_request_login_url` to mint a customer login URL. Required while the provider is enabled. Used by `moodle`. Example: my-login-token
     * @bodyParam default_role_id integer nullable Moodle role id assigned to provisioned users. Used by `moodle`. Example: 5
     * @bodyParam default_login_redirect_script string nullable Moodle redirect path after a shop-initiated login. Used by `moodle`. Example: /my/
     * @bodyParam sandbox boolean nullable Whether SpotPlayer licenses are issued in sandbox mode. Used by `spotplayer`. Example: false
     *
     * @responseFile 200 resources/responses/admin/settings/provisioning-providers/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     * @responseFile 422 resources/responses/422.json
     */
    public function update(Request $request, ProvisioningProviderSettingsEnum $provider): ApiResponseInterface
    {
        Gate::authorize('update', Setting::class);

        $dataClass = $provider->settingDataClass();
        $data      = $dataClass::validateAndCreate($dataClass::normalizePayload($request->all()));

        return apiResponse()->success($this->updateProviderSetting->handle($provider, $data));
    }
}
