<?php

declare(strict_types=1);

namespace App\Actions\Admin\DigitalAsset;

use App\Data\Admin\DigitalAsset\CreateDigitalAssetData;
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
            $mediaToAttach      = $data->media ?? [];
            $attachments        = $data->attachments ?: [];
            $categoriesToAttach = $data->categories ?? [];
            $digitalAsset->update($data->except('media', 'attachments', 'categories')->toArray());
            $digitalAsset->categories()->attach($categoriesToAttach);
            $digitalAsset->syncMedia(data_get($attachments, 'preview'), 'preview');
            $digitalAsset->syncMedia(data_get($attachments, 'main'), 'main');
            foreach (['gallery', 'video', 'cover'] as $tag) {
                $mediaIds = $mediaToAttach[$tag] ?? null;
                if (is_array($mediaIds)) {
                    $digitalAsset->syncMedia($mediaIds, $tag);
                }
            }
        });
    }
}
