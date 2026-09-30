<?php

declare(strict_types=1);

namespace App\Actions\Admin\LearningPath;

use App\Enums\Content\PublicationStatusEnum;
use App\Models\LearningPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class DeleteLearningPathAction
{
    public function handle(LearningPath $learningPath): void
    {
        if ($learningPath->status !== PublicationStatusEnum::DRAFT) {
            throw ValidationException::withMessages([
                'learning_path' => 'Only never-published learning path drafts can be deleted.',
            ]);
        }

        DB::transaction(function () use ($learningPath): void {
            $learningPath->detachMedia($learningPath->media()->get());
            $learningPath->delete();
        });
    }
}
