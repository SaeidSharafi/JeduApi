<?php

declare(strict_types=1);

namespace App\Actions\Admin\LearningPath;

use App\Enums\Content\PublicationStatusEnum;
use App\Models\LearningPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ArchiveLearningPathAction
{
    public function handle(LearningPath $learningPath): LearningPath
    {
        if ($learningPath->status !== PublicationStatusEnum::PUBLISHED) {
            throw ValidationException::withMessages([
                'status' => 'Only published learning paths can be archived.',
            ]);
        }

        return DB::transaction(function () use ($learningPath): LearningPath {
            $learningPath->update(['status' => PublicationStatusEnum::ARCHIVED]);

            return $learningPath->fresh();
        });
    }
}
