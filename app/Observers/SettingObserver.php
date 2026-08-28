<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Setting;
use App\Services\SettingsService;

final class SettingObserver
{
    public function saved(Setting $setting): void
    {
        app(SettingsService::class)->forget();
    }

    public function deleted(Setting $setting): void
    {
        app(SettingsService::class)->forget();
    }
}
