<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Setting;
use App\Observers\InvalidationObserver;
use App\Observers\SettingObserver;
use Illuminate\Support\ServiceProvider;

final class EventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Get the list of models from our config file
        $modelsToObserve = array_keys(config('cache_invalidation.map', []));

        // Automatically attach our single observer to every model in the list
        foreach ($modelsToObserve as $modelClass) {
            $modelClass::observe(InvalidationObserver::class);
        }

        Setting::observe(SettingObserver::class);
    }
}
