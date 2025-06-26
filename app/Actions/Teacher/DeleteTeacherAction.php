<?php

declare(strict_types=1);

namespace App\Actions\Teacher;

use App\Exceptions\ModelHasRelationshipDataException;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;

final readonly class DeleteTeacherAction
{
    /**
     * Execute the action.
     */
    public function handle(Teacher $teacher): void
    {
        DB::transaction(function () use ($teacher): void {
            if ($teacher->products()->exists()) {
                throw new ModelHasRelationshipDataException(relatedModel: Product::class);
            }
            $teacher->media()->delete();
            $teacher->delete();
        });
    }
}
