<?php

declare(strict_types=1);

namespace App\Actions\Admin\Category;

use App\Data\Admin\Category\SetGoodForStartData;
use App\Enums\System\MorphTypeEnum;
use App\Models\Categorizable;
use App\Models\Category;
use App\Services\CacheInvalidationService;

final readonly class SetGoodForStartAction
{
    public function __construct(
        private CacheInvalidationService $cacheInvalidator
    ) {}

    public function handle(Category $category, SetGoodForStartData $data): int
    {
        $count = Categorizable::query()
            ->where('category_id', $category->id)
            ->where('categorizable_type', MorphTypeEnum::COURSE)
            ->whereIn('categorizable_id', $data->course_ids) // Use the correct column
            ->update(['good_for_start' => $data->good_for_start]);
        if ($count > 0) {
            $invalidationConfig = config('cache_invalidation.map.'.Categorizable::class);

            if ($invalidationConfig) {
                $this->cacheInvalidator->invalidateForModel(Categorizable::class, $invalidationConfig);
            }
        }

        return $count;
    }
}
