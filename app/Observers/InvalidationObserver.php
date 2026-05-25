<?php

declare(strict_types=1);

namespace App\Observers;

use App\Services\CacheInvalidationService;
use Illuminate\Database\Eloquent\Model;

final class InvalidationObserver
{
    public function __construct(
        private readonly CacheInvalidationService $invalidationService,
    ) {}

    public function saved(Model $model): void
    {
        $this->invalidateCacheForModel($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidateCacheForModel($model);
    }

    private function invalidateCacheForModel(Model $model): void
    {
        $modelClass         = get_class($model);
        $invalidationConfig = config('cache_invalidation.map.'.$modelClass);

        if ($invalidationConfig && is_array($invalidationConfig)) {
            $this->invalidationService->invalidateForModel($model, $invalidationConfig);
        }
    }
}
