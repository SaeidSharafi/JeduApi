<?php

declare(strict_types=1);

namespace App\Actions\Admin\Course;

use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheTag;
use App\Enums\System\MorphTypeEnum;
use App\Exceptions\ModelHasRelationshipDataException;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\LearningPathStep;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

final readonly class DeleteCourseAction
{
    public function __construct(private CacheStore $cache) {}

    /**
     * Execute the action.
     */
    public function handle(Course $course): void
    {
        DB::transaction(function () use ($course): void {
            if ($course->products()->exists()) {
                throw new ModelHasRelationshipDataException(Product::class);
            }
            if (LearningPathStep::query()
                ->where('productable_type', MorphTypeEnum::COURSE->value)
                ->where('productable_id', $course->getKey())
                ->exists()) {
                throw new ModelHasRelationshipDataException(LearningPath::class);
            }
            $course->media()->delete();
            $course->digitalAssets()->detach();
            $course->categories()->detach();
            $course->delete();
        });

        $this->cache->invalidate(CacheTag::HomePage, CacheTag::Catalog);
    }
}
