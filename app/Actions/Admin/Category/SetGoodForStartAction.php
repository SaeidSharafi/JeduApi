<?php

declare(strict_types=1);

namespace App\Actions\Admin\Category;

use App\Data\Admin\Category\SetGoodForStartData;
use App\Enums\MorphTypeEnum;
use App\Models\Categorizable;
use App\Models\Category;

final readonly class SetGoodForStartAction
{
    public function handle(Category $category, SetGoodForStartData $data): bool|int
    {
        return Categorizable::query()
            ->where('category_id', $category->id)
            ->where('categorizable_type', MorphTypeEnum::COURSE)
            ->whereIn('categorizable_id', $data->course_ids) // Use the correct column
            ->update(['good_for_start' => $data->good_for_start]);
    }
}
