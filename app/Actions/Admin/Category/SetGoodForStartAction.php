<?php

declare(strict_types=1);

namespace App\Actions\Admin\Category;

use App\Contracts\Cache\CacheStore;
use App\Data\Admin\Category\SetGoodForStartData;
use App\Enums\System\CacheTag;
use App\Enums\System\MorphTypeEnum;
use App\Models\Categorizable;
use App\Models\Category;

final readonly class SetGoodForStartAction
{
    public function __construct(private CacheStore $cache) {}

    public function handle(Category $category, SetGoodForStartData $data): int
    {
        $count = Categorizable::query()
            ->where('category_id', $category->id)
            ->where('categorizable_type', MorphTypeEnum::COURSE)
            ->whereIn('categorizable_id', $data->course_ids) // Use the correct column
            ->update(['good_for_start' => $data->good_for_start]);

        if ($count > 0) {
            $this->cache->invalidate(CacheTag::Catalog);
        }

        return $count;
    }
}
