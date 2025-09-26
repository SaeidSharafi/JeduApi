<?php

declare(strict_types=1);

namespace App\Models;

use App\Data\Admin\MediaData;
use App\Enums\System\SettingKeyEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Plank\Mediable\Media;
use Plank\Mediable\Mediable;

final class Setting extends Model
{
    use HasFactory;
    use Mediable;

    protected $fillable
        = [
            'key',
            'value',
            'type',
            'group',
        ];

    /**
     * Get a setting value by key.
     */
    public static function getValue(SettingKeyEnum $key, mixed $default = null): mixed
    {
        $setting = self::where('key', $key->value)->first();
        // Make sure we have an array to work with before passing to witImages
        $value = $setting ? $setting->value : $default;

        // Only process if the value is a non-empty array
        if (is_array($value) && ! empty($value)) {
            return self::witImages($value);
        }

        return $value;
    }

    /**
     * Set a setting value by key.
     */
    public static function setValue(SettingKeyEnum $key, mixed $value, string $type = 'json', ?string $group = null): static
    {
        return self::updateOrCreate(
            ['key' => $key->value],
            [
                'value' => $value,
                'type'  => $type,
                'group' => $group,
            ]
        );
    }

    /**
     * Recursively finds keys related to media, fetches the corresponding Media
     * models, and replaces the IDs with MediaData DTOs.
     *
     * @param  array  $settingData  The array to process.
     * @return array The processed array with DTOs.
     */
    public static function witImages(array $settingData): array
    {
        // 1. Exact key names for a single media ID.
        $singularMediaKeys = ['image', 'icon', 'logo', 'file', 'media'];

        // 2. Suffixes for a single media ID. Matches 'image_id', 'background_id', etc.
        $singularMediaSuffixes = ['_id', '_icon', '_image'];

        // 3. Exact key names for an array of media IDs.
        $pluralMediaKeys = ['images', 'icons', 'gallery', 'files'];

        // 4. Suffixes for an array of media IDs. Matches 'image_ids', 'gallery_images', etc.
        $pluralMediaSuffixes = ['_ids', '_images'];

        $imageIds = [];

        // This closure checks if a key matches our defined conventions.
        $isSingularMediaKey = fn ($key): bool => in_array($key, $singularMediaKeys) || Str::endsWith($key, $singularMediaSuffixes);
        $isPluralMediaKey   = fn ($key): bool => in_array($key, $pluralMediaKeys) || Str::endsWith($key, $pluralMediaSuffixes);

        array_walk_recursive($settingData, function ($value, $key) use (&$imageIds, $isSingularMediaKey): void {
            // Find single IDs (e.g., "icon": 3 or "image_id": 3)
            if (is_string($key) && $isSingularMediaKey($key) && is_numeric($value)) {
                $imageIds[] = (int) $value;
            }
        });

        // The walker above doesn't check parent array keys, so we do a separate non-recursive
        // loop to find arrays of IDs (e.g., "images": [1, 2]).
        foreach ($settingData as $key => $value) {
            if (is_string($key) && $isPluralMediaKey($key) && is_array($value)) {
                foreach ($value as $id) {
                    if (is_numeric($id)) {
                        $imageIds[] = (int) $id;
                    }
                }
            }
        }

        if (empty($imageIds)) {
            return $settingData;
        }

        // STEP 2: Fetch all unique media models in one efficient query.
        $images = Media::find(array_unique($imageIds))->keyBy('id');

        // STEP 3: Define a recursive function to replace IDs with DTOs.
        $replacer = function (&$array) use ($images, $isSingularMediaKey, $isPluralMediaKey, &$replacer): void {
            foreach ($array as $key => &$value) {
                if (! is_string($key)) {
                    continue; // Skip numeric keys
                }

                if ($isPluralMediaKey($key) && is_array($value)) {
                    // It's an array of IDs. Replace it with DTOs.
                    $value = collect($value)
                        ->map(fn ($id): ?\App\Data\Admin\MediaData => $images->get($id) ? MediaData::fromModel($images->get($id)) : null)
                        ->filter()
                        ->values()
                        ->all();
                } elseif ($isSingularMediaKey($key) && is_numeric($value)) {
                    // It's a single ID. Replace it with a DTO or null.
                    $value = $images->get($value) ? MediaData::fromModel($images->get($value)) : null;
                } elseif (is_array($value)) {
                    // It's another block, so we go deeper.
                    $replacer($value);
                }
            }
        };

        // STEP 4: Run the replacer and return the modified array.
        $replacer($settingData);

        return $settingData;
    }

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
