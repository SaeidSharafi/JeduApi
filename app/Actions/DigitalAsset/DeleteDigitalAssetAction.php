<?php

declare(strict_types=1);

namespace App\Actions\DigitalAsset;

use App\Models\DigitalAsset;
use Illuminate\Support\Facades\DB;

final readonly class DeleteDigitalAssetAction
{
    /**
     * Execute the action.
     */
    public function handle(DigitalAsset $digitalAsset): void
    {
        DB::transaction(function () use ($digitalAsset): void {
            $digitalAsset->media()->delete();
            $digitalAsset->delete();
        });
    }
}
