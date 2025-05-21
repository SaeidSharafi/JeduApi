<?php

declare(strict_types=1);

namespace App\Actions\Category;

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
            $category->media()->delete();
            $category->delete();
        });
    }
}
