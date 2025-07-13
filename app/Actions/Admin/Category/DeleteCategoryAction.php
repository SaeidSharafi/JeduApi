<?php

declare(strict_types=1);

namespace App\Actions\Admin\Category;

use App\Exceptions\ModelHasRelationshipDataException;
use App\Models\Categorizable;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

final readonly class DeleteCategoryAction
{
    /**
     * Execute the action.
     */
    public function handle(Category $category): void
    {
        DB::transaction(function () use ($category): void {
            if ($category->categorizable()->exists()) {
                throw new ModelHasRelationshipDataException(
                    Categorizable::class,
                    message: __('messages.errors.model_has_relationship_data_without_related_model')
                );
            }
            $category->media()->delete();
            $category->delete();
        });
    }
}
