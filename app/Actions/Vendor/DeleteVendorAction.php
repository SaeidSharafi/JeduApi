<?php

declare(strict_types=1);

namespace App\Actions\Vendor;

use App\Data\Vendor\CreateVendorData;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Plank\Mediable\Media;

final readonly class DeleteVendorAction
{
    /**
     * Execute the action.
     */
    public function handle(Vendor $vendor): void
    {
        DB::transaction(function () use($vendor): void {
            $vendor->media()->delete();
            $vendor->delete();
        });
    }
}
