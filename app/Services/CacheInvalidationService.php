<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\System\CacheKeysEnum;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use SmartCache\Facades\SmartCache;

/**
 * Simple Cache Invalidation Service
 *
 * Handles cache invalidation for model changes using:
 * - Direct key removal: forget($key)
 * - Pattern-based removal: flushPatterns(['pattern:*'])
 *
 * This is intentionally simple - no tags (memory leak risk),
 * no strategy pattern (overkill), just straightforward invalidation.
 */
final class CacheInvalidationService
{
    /**
     * Invalidate caches for a model based on configuration.
     *
     * @param  Model  $model  The model that was changed
     * @param  array  $invalidationConfig  Array of cache keys/patterns to invalidate
     *
     * Example config:
     * ```
     * [
     *     CacheKeysEnum::HomePageContent,                                   // Enum: converted to string value
     *     'shop.homepage.content',                                          // Direct key
     *     ['type' => 'pattern', 'value' => 'shop.category.*.good-for-start.*'], // Pattern
     * ]
     * ```
     */
    /**
     * @param  array<string, mixed>  $invalidationConfig
     */
    public function invalidateForModel(string|Model $model, array $invalidationConfig): void
    {
        // Separate direct keys from patterns for batch processing
        $directKeys = [];
        $patterns   = [];
        $class      = is_string($model) ? $model : get_class($model);
        foreach ($invalidationConfig as $config) {
            // CacheKeysEnum instance - convert to string value
            if ($config instanceof CacheKeysEnum) {
                $directKeys[] = $config->value;
            }
            // String literal = direct key
            elseif (is_string($config)) {
                $directKeys[] = $config;
            }
            // Array with 'type' => 'pattern'
            elseif (is_array($config) && ($config['type'] ?? null) === 'pattern') {
                $patterns[] = $config['value'];
            }
        }

        // Clear direct keys
        if (! empty($directKeys)) {
            try {
                foreach ($directKeys as $key) {
                    SmartCache::forget($key);
                }
            } catch (Exception $e) {
                Log::debug(
                    "Cache invalidation failed for direct keys on {$class}",
                    ['keys' => $directKeys, 'error' => $e->getMessage()]
                );
            }
        }

        // Clear pattern-based caches
        if (! empty($patterns)) {
            try {
                SmartCache::flushPatterns($patterns);
            } catch (Exception $e) {
                Log::debug(
                    "Cache invalidation failed for patterns on {$class}",
                    ['patterns' => $patterns, 'error' => $e->getMessage()]
                );
            }
        }
    }
}
