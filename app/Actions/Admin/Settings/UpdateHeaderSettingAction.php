<?php

declare(strict_types=1);

namespace App\Actions\Admin\Settings;

use App\Data\Admin\Settings\HeaderCreateData;
use App\Data\Admin\Settings\HeaderData;
use App\Enums\System\SettingKeyEnum;
use App\Services\SettingsService;
use Plank\Mediable\Media;

final class UpdateHeaderSettingAction
{
    public function __construct(private readonly SettingsService $settingsService) {}

    public function handle(HeaderCreateData $data): HeaderData
    {
        $header = $data->toArray();

        $links = $header['navigation_links'] ?? [];
        usort($links, fn ($a, $b): int => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
        $header['navigation_links'] = array_values($links);

        $logo               = $data->logo ? Media::find($data->logo) : null;
        $header['logo_url'] = $logo?->getUrl();

        $setting = $this->settingsService->set(SettingKeyEnum::HEADER, $header, 'json', 'site');
        $setting->syncMedia($logo, 'logo');

        $this->settingsService->forget();

        return HeaderData::from(
            $this->settingsService->get(SettingKeyEnum::HEADER, HeaderData::getDefaults())
        );
    }
}
