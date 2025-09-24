<?php

namespace App\Services;

use App\Enums\CacheKeysEnum;
use App\Models\Setting;
use Illuminate\Support\Collection;
use SmartCache\Facades\SmartCache;

class SettingsService
{

    /**
     * Retrieve a setting value by key.
     *
     * Returns the setting value for the given key, or $default if the key does not exist.
     * If the stored value is a non-empty array, it is processed with Setting::witImages() before returning.
     *
     * @param string $key The setting key to look up.
     * @param mixed|null $default Value to return when the setting is not found.
     * @return mixed The processed setting value or the provided default.
     */
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
     * Clears the cached application settings.
     *
     * Removes the settings entry from the cache so future reads will reload settings from persistent storage.
     */
    public function forget(): void
    {
        SmartCache::forget(CacheKeysEnum::Settings->value);
    }

    /**
     * Retrieve all settings keyed by their 'key', cached indefinitely.
     *
     * Loads all Setting records, keys them by the 'key' attribute, and stores the
     * resulting Collection in the cache forever under the Settings cache key.
     * Returns the cached Collection when present; on cache miss the database is
     * queried once to populate the cache.
     *
     * @return Collection<string, Setting> Collection of Setting models keyed by setting key.
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
