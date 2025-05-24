<?php

declare(strict_types=1);

namespace App\Actions\File;

use App\Data\File\CreateDigitalAssetData;
use App\Models\DigitalAsset;
use Illuminate\Support\Facades\DB;

final readonly class UpdateDigitalAssetAction
{
    /**
     * Execute the action.
     */
    public function handle(CreateDigitalAssetData $data, DigitalAsset $digitalAsset): void
    {
        DB::transaction(function () use ($digitalAsset, $data): void {
            $attachments = $data->attachments ?: [];
            $digitalAsset->update($data->except('attachments')->toArray());
            $digitalAsset->syncMedia(data_get($attachments, 'preview'), 'preview');
            $digitalAsset->syncMedia(data_get($attachments, 'main'), 'main');
        });
    }
}
