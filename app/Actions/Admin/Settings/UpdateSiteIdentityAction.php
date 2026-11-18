<?php

declare(strict_types=1);

namespace App\Actions\Admin\Settings;

use App\Data\Admin\Settings\SiteIdentityData;
use App\Data\Admin\Settings\SiteIdentityUpdateData;
use App\Enums\System\SettingKeyEnum;
use App\Services\SettingsService;
use Illuminate\Validation\ValidationException;
use Plank\Mediable\Media;
use Spatie\LaravelData\Optional;

final class UpdateSiteIdentityAction
{
    public function __construct(private readonly SettingsService $settingsService) {}

    public function handle(SiteIdentityUpdateData $data): SiteIdentityData
    {
        $favicon = $data->favicon !== null ? Media::find($data->favicon) : null;

        if ($data->favicon !== null && ! $this->isImage($favicon)) {
            // A PDF or archive would upload fine but no browser would render it as a tab icon.
            throw ValidationException::withMessages([
                'favicon' => __('messages.favicon_must_be_image'),
            ]);
        }

        $existing = $this->settingsService->get(SettingKeyEnum::SITE_IDENTITY, SiteIdentityData::getDefaults());
        $identity = [
            'site_title'  => mb_trim($data->site_title),
            'favicon'     => $favicon?->id,
            'favicon_url' => $favicon?->getUrl(),
        ];

        foreach (['home_h1', 'home_description', 'home_meta_title', 'home_meta_description'] as $field) {
            $identity[$field] = $data->{$field} instanceof Optional ? ($existing[$field] ?? null) : $data->{$field};
        }

        $setting = $this->settingsService->set(SettingKeyEnum::SITE_IDENTITY, $identity, 'json', 'site');
        $setting->syncMedia($favicon, 'favicon');

        return SiteIdentityData::from(
            $this->settingsService->get(SettingKeyEnum::SITE_IDENTITY, SiteIdentityData::getDefaults())
        );
    }

    private function isImage(?Media $favicon): bool
    {
        return $favicon !== null && str_starts_with((string) $favicon->mime_type, 'image/');
    }
}
