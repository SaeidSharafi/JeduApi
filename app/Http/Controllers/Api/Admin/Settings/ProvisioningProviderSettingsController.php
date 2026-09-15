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
     * - `key` (string): Provider identifier. Currently `ims`.
     * - `label` (string): Localized display label.
     * - `state` (object): `enabled`, `configured` (every required field is filled) and `ready` (`enabled && configured`).
     * - `schema` (object): Grouped field definitions. Groups: `general`, `connection`, `credentials`, `advanced`.
     * - `settings` (object): Effective values. `api_key` is always masked.
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
     * the list. BBB and Moodle Quiz are not offered at all, so their keys and
     * any unknown key are `404`.
     *
     * @urlParam provider string required The provider key. Enum: `ims`. Example: ims
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
     * owns the request, so its parameters are documented here for Scribe. Non-secret
     * fields follow full-replace semantics: send the whole object from the form,
     * and any field that is omitted or cleared falls back to its configured
     * default. To keep the stored API key, omit `api_key` or send `null`
     * (sending the masked placeholder back also keeps it); an explicit empty
     * string clears it.
     *
     * `enabled` is always required. A provider may be saved disabled with empty
     * connection fields so it can be staged, but turning it on without every
     * required field is rejected with a `422` naming the offending fields.
     *
     * @urlParam provider string required The provider key. Enum: `ims`. Example: ims
     *
     * @bodyParam enabled boolean required Whether the provider may be used for provisioning. Example: true
     * @bodyParam base_url string The IMS base URL. Required while the provider is enabled. Example: https://ims.example.ir
     * @bodyParam api_key string Provider API key. Omit or send `null` (or the masked placeholder) to keep the stored key; send an empty string to clear it. Required while the provider is enabled. Example: my-api-key
     * @bodyParam timeout integer Optional request timeout in seconds. Example: 15
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
