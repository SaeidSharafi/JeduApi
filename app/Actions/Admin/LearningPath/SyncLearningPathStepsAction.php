<?php

declare(strict_types=1);

namespace App\Actions\Admin\LearningPath;

use App\Models\LearningPath;

final readonly class SyncLearningPathStepsAction
{
    /**
     * @param  array<int, array<string, mixed>>  $steps
     */
    public function handle(LearningPath $learningPath, array $steps): void
    {
        $learningPath->steps()->delete();
        usort($steps, static fn (array $left, array $right): int => $left['position'] <=> $right['position']);

        $learningPath->steps()->createMany($steps);
    }
}
