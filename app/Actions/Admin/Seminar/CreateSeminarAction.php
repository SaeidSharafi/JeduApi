<?php

declare(strict_types=1);

namespace App\Actions\Admin\Seminar;

use App\Actions\Admin\GetThumbnailUrlAction;
use App\Contracts\Cache\CacheStore;
use App\Data\Admin\Seminar\CreateSeminarData;
use App\Enums\System\CacheTag;
use App\Models\Seminar;

final class CreateSeminarAction
{
    public function __construct(
        private GetThumbnailUrlAction $thumbnailUrlAction,
        private readonly CacheStore $cache,
    ) {}

    public function handle(CreateSeminarData $data): void
    {
        $mediaToAttach                  = $data->media;
        $categoriesToAttach             = $data->categories;
        $digitalAssetsAttach            = $data->digital_assets;
        $valdiatedData                  = $data->except('media', 'categories', 'digital_assets')->toArray();
        $valdiatedData['thumbnail_url'] = $this->thumbnailUrlAction->handle($data->media);
        $seminar                        = Seminar::query()->create($valdiatedData);
        $seminar->categories()->attach($categoriesToAttach);
        $seminar->digitalAssets()->attach($digitalAssetsAttach);
        foreach ($mediaToAttach as $tag => $mediaIds) {
            $seminar->attachMedia($mediaIds, $tag);
        }

        // The model observer that used to clear search on a Seminar save is gone.
        $this->cache->invalidate(CacheTag::Search);
    }
}
