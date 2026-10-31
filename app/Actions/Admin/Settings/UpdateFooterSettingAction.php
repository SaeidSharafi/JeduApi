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
        $validated['logo_alt'] = $logo->alt       ?? null;

        // Badge images are picked as media in the panel, so the storefront-facing
        // address is resolved here — the same way logo_url is — instead of on read.
        $badges = Media::find(array_filter(array_column($data->certifications ?? [], 'image')))->keyBy('id');

        $validated['certifications'] = array_map(
            static fn (array $certification): array => [
                ...$certification,
                'image_url' => $badges->get($certification['image'] ?? 0)?->getUrl(),
            ],
            $data->certifications ?? [],
        );

        $setting = $this->settingsService->set(SettingKeyEnum::FOOTER, $validated, 'json', 'site');
        $setting->syncMedia($logo, 'logo');

        $this->settingsService->forget();

        return FooterData::from(
            $this->settingsService->get(SettingKeyEnum::FOOTER, FooterData::getDefaults())
        );
    }
}
