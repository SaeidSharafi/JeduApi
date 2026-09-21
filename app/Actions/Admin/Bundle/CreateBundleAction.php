<?php

declare(strict_types=1);

namespace App\Actions\Admin\Bundle;

use App\Actions\Admin\GetThumbnailUrlAction;
use App\Contracts\Cache\CacheStore;
use App\Data\Admin\Bundle\BundleCreateData;
use App\Enums\System\CacheTag;
use App\Models\Bundle;
use Illuminate\Support\Facades\DB;

final readonly class CreateBundleAction
{
    public function __construct(
        private GetThumbnailUrlAction $thumbnailUrlAction,
        private CacheStore $cache,
    ) {}

    public function handle(BundleCreateData $data): Bundle
    {
        $bundle = DB::transaction(function () use ($data): Bundle {
            $mediaToAttach               = $data->media;
            $bundleData                  = $data->except('media')->toArray();
            $bundleData['thumbnail_url'] = $this->thumbnailUrlAction->handle($mediaToAttach);

            $bundle = Bundle::create($bundleData);

            foreach ($mediaToAttach as $tag => $mediaIds) {
                if (is_array($mediaIds)) {
                    foreach ($mediaIds as $mediaId) {
                        $bundle->attachMedia($mediaId, $tag);
                    }
                }
            }

            return $bundle;
        });

        $this->cache->invalidate(CacheTag::Search);

        return $bundle;
    }
}
