<?php

declare(strict_types=1);

namespace App\Models;

use App\Data\Admin\MediaData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Plank\Mediable\Media;

class Setting extends Model
{
    use HasFactory;

    protected $fillable
        = [
            'key',
            'value',
            'type',
            'group',
        ];

    protected $casts
        = [
            'value' => 'array',
        ];

    /**
     * Get a setting value by key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        // Make sure we have an array to work with before passing to witImages
        $value = $setting ? $setting->value : $default;

        // Only process if the value is a non-empty array
        if (is_array($value) && !empty($value)) {
            return self::witImages($value);
        }

        return $value;
    }

    /**
     * Set a setting value by key.
     */
    public static function set(string $key, mixed $value, string $type = 'json', ?string $group = null): static
    {
        return static::updateOrCreate(
            ['key' => $key],
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
     * @param array $settingData The array to process.
     * @return array The processed array with DTOs.
     */
    public static function witImages(array $settingData): array
    {
        // Define the keywords that identify a key as being media-related.
        // You can easily add more keywords here (e.g., 'logo', 'banner', 'gallery').
        $mediaKeywords = ['image', 'icon', 'media', 'file'];

        // 1. Collect all media IDs in a single pass.
        $imageIds = [];
        array_walk_recursive($settingData, function ($value, $key) use (&$imageIds, $mediaKeywords) {
            // Check if the KEY contains a media keyword and the VALUE is a valid ID.
            if (is_string($key) && Str::contains($key, $mediaKeywords) && is_numeric($value)) {
                $imageIds[] = (int) $value;
            }
        });

        // This handles cases where the key itself is an array of IDs (e.g., "images" => [1, 2]).
        // The recursive walker above only gets the numeric values, not the parent key.
        // So we need a separate, non-recursive check on the top-level keys.
        foreach ($settingData as $key => $value) {
            if (is_string($key) && Str::contains($key, $mediaKeywords) && is_array($value)) {
                foreach ($value as $id) {
                    if (is_numeric($id)) {
                        $imageIds[] = (int) $id;
                    }
                }
            }
        }

        if (empty($imageIds)) {
            return $settingData; // No IDs found, nothing to do.
        }

        // 2. Fetch all unique media models in one efficient query.
        $images = Media::find(array_unique($imageIds))->keyBy('id');

        // 3. Define a recursive function to replace IDs with DTOs.
        // It modifies the array by reference (&) for efficiency.
        $replacer = function (&$array) use ($images, $mediaKeywords, &$replacer) {
            foreach ($array as $key => &$value) { // Note the '&' on $value
                if (!is_string($key)) {
                    continue; // Skip numeric keys in indexed arrays
                }

                $isMediaKey = Str::contains($key, $mediaKeywords);

                if ($isMediaKey && is_array($value)) {
                    // It's an array of IDs (e.g., "images": [1, 2])
                    // Replace the entire array with DTOs.
                    $value = collect($value)
                        ->map(fn($id) => isset($images[$id]) ? MediaData::fromModel($images[$id]) : null)
                        ->filter() // Remove nulls for non-existent media
                        ->values()
                        ->all();
                } elseif ($isMediaKey && is_numeric($value)) {
                    // It's a single ID (e.g., "icon": 3)
                    // Replace the ID with a DTO or null.
                    $value = isset($images[$value]) ? MediaData::fromModel($images[$value]) : null;
                } elseif (is_array($value)) {
                    // It's a regular block, so we go deeper.
                    $replacer($value);
                }
            }
        };

        // 4. Run the replacer and return the modified array.
        $replacer($settingData);

        return $settingData;
    }

}
