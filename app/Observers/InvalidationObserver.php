<?php

declare(strict_types=1);

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use SmartCache\Facades\SmartCache;

final class InvalidationObserver
{
    public function saved(Model $model): void
    {
        $this->clearCacheForModel($model);
    }

    public function deleted(Model $model): void
    {
        $this->clearCacheForModel($model);
    }

    private function clearCacheForModel(Model $model): void
    {
        // Get the class of the model that changed (e.g., "App\Models\Product")
        $modelClass = get_class($model);

        // Look up this class in our config map
        $keysToClear = config('cache_invalidation.map.'.$modelClass);

        // If it's in our map, clear all associated cache keys
        if ($keysToClear && is_array($keysToClear)) {
            foreach ($keysToClear as $key) {
                SmartCache::forget($key->key());
            }
        }
    }
}
