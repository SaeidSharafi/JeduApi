<?php

declare(strict_types=1);

namespace App\Actions\File;

use App\Data\File\CreateDigitalAssetData;
use App\Models\DigitalAsset;
use Illuminate\Support\Facades\DB;

final readonly class CreateDigitalAssetAction
{
    /**
     * Execute the action.
     */
    public function handle(CreateDigitalAssetData $data): void
    {
        DB::transaction(function () use ($data): void {
            $attachments = $data->attachments ?: [];
            $digitalAsset = DigitalAsset::query()->create($data->except('attachments')->toArray())->fresh();
            if ($preview = data_get($attachments, 'preview')) {
                $digitalAsset->syncMedia($preview, 'preview');
            }
            $digitalAsset->syncMedia(data_get($attachments, 'main'), 'main');

        });
    }
}
