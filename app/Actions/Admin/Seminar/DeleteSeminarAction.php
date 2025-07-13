<?php

declare(strict_types=1);

namespace App\Actions\Admin\Seminar;

use App\Exceptions\ModelHasRelationshipDataException;
use App\Models\Product;
use App\Models\Seminar;
use Illuminate\Support\Facades\DB;

final readonly class DeleteSeminarAction
{
    /**
     * Execute the action.
     */
    public function handle(Seminar $seminar): void
    {
        DB::transaction(function () use ($seminar): void {
            if ($seminar->products()->exists()) {
                throw new ModelHasRelationshipDataException(Product::class);
            }
            $seminar->media()->delete();
            $seminar->digitalAssets()->detach();
            $seminar->categories()->detach();
            $seminar->delete();
        });
    }
}
