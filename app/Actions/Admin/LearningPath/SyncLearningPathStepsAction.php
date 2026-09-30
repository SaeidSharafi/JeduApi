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

        foreach ($steps as $step) {
            $learningPath->steps()->create([
                'position'         => $step['position'],
                'productable_type' => $step['productable_type'],
                'productable_id'   => $step['productable_id'],
                'title'            => $step['title'],
                'description'      => $step['description'],
            ]);
        }
    }
}
