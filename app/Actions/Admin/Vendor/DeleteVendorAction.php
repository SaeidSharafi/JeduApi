<?php

declare(strict_types=1);

namespace App\Actions\Admin\Vendor;

use App\Exceptions\ModelHasRelationshipDataException;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

final readonly class DeleteVendorAction
{
    /**
     * Execute the action.
     */
    public function handle(Vendor $vendor): void
    {
        DB::transaction(function () use ($vendor): void {
            if ($vendor->products()->exists()) {
                throw new ModelHasRelationshipDataException(Product::class);
            }
            $vendor->media()->delete();
            $vendor->delete();
        });
    }
}
