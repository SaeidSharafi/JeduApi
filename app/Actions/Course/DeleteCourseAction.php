<?php

declare(strict_types=1);

namespace App\Actions\Course;

use App\Models\Course;
use Illuminate\Support\Facades\DB;

final readonly class DeleteCourseAction
{
    /**
     * Execute the action.
     */
    public function handle(Course $course): void
    {
        DB::transaction(function () use($course): void {
            $course->media()->delete();
            $course->delete();
        });
    }
}
