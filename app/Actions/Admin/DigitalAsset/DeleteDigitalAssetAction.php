<?php

declare(strict_types=1);

namespace App\Actions\Admin\DigitalAsset;

use App\Exceptions\ModelHasRelationshipDataException;
use App\Models\DigitalAsset;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

final readonly class DeleteDigitalAssetAction
{
    /**
     * Execute the action.
     */
    public function handle(DigitalAsset $digitalAsset): void
    {
        DB::transaction(function () use ($digitalAsset): void {
            if ($digitalAsset->products()->exists()) {
                throw new ModelHasRelationshipDataException(Product::class);
            }
            $digitalAsset->media()->delete();
            $digitalAsset->delete();
        });
    }
}
