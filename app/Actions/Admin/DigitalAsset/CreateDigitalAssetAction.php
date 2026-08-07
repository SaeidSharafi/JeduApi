<?php

declare(strict_types=1);

namespace App\Actions\Admin\DigitalAsset;

use App\Actions\Admin\GetThumbnailUrlAction;
use App\Data\Admin\DigitalAsset\CreateDigitalAssetData;
use App\Models\DigitalAsset;
use Illuminate\Support\Facades\DB;

final readonly class CreateDigitalAssetAction
{
    public function __construct(
        private GetThumbnailUrlAction $thumbnailUrlAction
    ) {}

    /**
     * Execute the action.
     */
    public function handle(CreateDigitalAssetData $data): void
    {
        DB::transaction(function () use ($data): void {
            $attachments                    = $data->attachments;
            $categoriesToAttach             = $data->categories ;
            $mediaToAttach                  = $data->media      ;
            $valdiatedData                  = $data->except('media', 'attachments', 'categories')->toArray();
            $valdiatedData['thumbnail_url'] = $this->thumbnailUrlAction->handle($data->media);
            $digitalAsset                   = DigitalAsset::query()
                ->create($valdiatedData)
                ->fresh();
            $digitalAsset->categories()->attach($categoriesToAttach);
            if ($preview = data_get($attachments, 'preview')) {
                $digitalAsset->syncMedia($preview, 'preview');
            }
            $digitalAsset->syncMedia(data_get($attachments, 'main'), 'main');
            foreach ($mediaToAttach as $tag => $mediaIds) {
                $digitalAsset->attachMedia($mediaIds, $tag);
            }
        });
    }
}
