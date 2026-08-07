<?php

declare(strict_types=1);

namespace App\Actions\Admin\Settings;

use App\Data\Admin\Settings\FooterCreateData;
use App\Data\Admin\Settings\FooterData;
use App\Enums\System\SettingKeyEnum;
use App\Services\SettingsService;
use Plank\Mediable\Media;

final class UpdateFooterSettingAction
{
    public function __construct(private readonly SettingsService $settingsService) {}

    public function handle(FooterCreateData $data): FooterData
    {
        $logo      = null;
        $validated = $data->toArray();

        if ($data->logo !== null) {
            $logo = Media::find($data->logo);
        }

        $validated['logo_url'] = $logo?->getUrl() ?? null;
        $validated['logo_alt'] = $logo->alt ?? null;

        $setting = $this->settingsService->set(SettingKeyEnum::FOOTER, $validated, 'json', 'site');
        $setting->syncMedia($logo, 'logo');

        $this->settingsService->forget();

        return FooterData::from(
            $this->settingsService->get(SettingKeyEnum::FOOTER, FooterData::getDefaults())
        );
    }
}
