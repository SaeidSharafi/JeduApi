<?php

namespace App\Services;

use App\Enums\CacheKeysEnum;
use App\Models\Setting;
use Illuminate\Support\Collection;
use SmartCache\Facades\SmartCache;

class SettingsService
{

    public function get(string $key, mixed $default = null): mixed
    {
        $allSettings = $this->getAll();

        // Retrieve the specific setting model from the collection.
        $setting = $allSettings->get($key);

        // If the setting doesn't exist, return the default.
        if (!$setting) {
            return $default;
        }

        // The value is an array, so we can process it with our image logic.
        $value = $setting->value;
        if (is_array($value) && !empty($value)) {
            // Your powerful witImages logic is still used here!
            return Setting::witImages($value);
        }

        return $value;
    }

    /**
     * Forgets the settings cache. This is our public method for invalidation.
     */
    public function forget(): void
    {
        SmartCache::forget(CacheKeysEnum::Settings->value);
    }

    /**
     * Retrieves the entire collection of settings.
     * If not in the cache, it loads from the DB and caches it forever.
     */
    private function getAll(): Collection
    {
        return SmartCache::rememberForever(CacheKeysEnum::Settings->value, function () {
            // This closure only runs if the cache is empty.
            // It hits the database ONCE and then stores the result.
            return Setting::all()->keyBy('key');
        });
    }

}
