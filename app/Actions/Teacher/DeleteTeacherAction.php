<?php

declare(strict_types=1);

namespace App\Actions\Teacher;

use App\Data\Teacher\CreateTeacherData;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;

final readonly class DeleteTeacherAction
{
    /**
     * Execute the action.
     */
    public function handle(Teacher $teacher): void
    {
        DB::transaction(function () use($teacher): void {
            $teacher->media()->delete();
            $teacher->delete();
        });
    }
}
