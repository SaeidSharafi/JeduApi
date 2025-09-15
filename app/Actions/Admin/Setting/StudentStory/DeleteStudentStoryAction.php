<?php

declare(strict_types=1);

namespace App\Actions\Admin\Setting\StudentStory;

use App\Models\StudentStory;
use Illuminate\Support\Facades\DB;

final class DeleteStudentStoryAction
{
    public function handle(StudentStory $story): void
    {
        DB::transaction(function () use ($story): void {
            $story->media()->delete();
            $story->delete();
        });
    }
}
