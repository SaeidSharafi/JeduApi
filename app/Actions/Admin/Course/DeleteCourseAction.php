<?php

declare(strict_types=1);

namespace App\Actions\Admin\Course;

use App\Exceptions\ModelHasRelationshipDataException;
use App\Models\Course;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

final readonly class DeleteCourseAction
{
    /**
     * Execute the action.
     */
    public function handle(Course $course): void
    {
        DB::transaction(function () use ($course): void {
            if ($course->products()->exists()) {
                throw new ModelHasRelationshipDataException(Product::class);
            }
            $course->media()->delete();
            $course->digitalAssets()->detach();
            $course->categories()->detach();
            $course->delete();
        });
    }
}
