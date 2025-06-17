<?php

declare(strict_types=1);

namespace App\Actions\Vendor;

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
            $vendor->media()->delete();
            $vendor->delete();
        });
    }
}
