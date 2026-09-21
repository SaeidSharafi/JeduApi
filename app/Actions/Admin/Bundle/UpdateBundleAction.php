<?php

declare(strict_types=1);

namespace App\Actions\Admin\Bundle;

use App\Actions\Admin\GetThumbnailUrlAction;
use App\Contracts\Cache\CacheStore;
use App\Data\Admin\Bundle\BundleUpdateData;
use App\Enums\MediaTagEnum;
use App\Enums\System\CacheTag;
use App\Models\Bundle;
use Illuminate\Support\Facades\DB;

final readonly class UpdateBundleAction
{
    public function __construct(
        private GetThumbnailUrlAction $thumbnailUrlAction,
        private CacheStore $cache,
    ) {}

    public function handle(BundleUpdateData $data, Bundle $bundle): Bundle
    {
        $bundle = DB::transaction(function () use ($data, $bundle): Bundle {
            $bundleData                  = $data->except('media')->toArray();
            $bundleData['thumbnail_url'] = $this->thumbnailUrlAction->handle($data->media);
            $bundle->update($bundleData);

            foreach (MediaTagEnum::getAllValues() as $tag) {
                $mediaIds = $data->media[$tag] ?? null;
                if (is_array($mediaIds)) {
                    $bundle->syncMedia($mediaIds, $tag);
                }
            }

            return $bundle->fresh();
        });

        $this->cache->invalidate(CacheTag::Search);

        return $bundle;
    }
}
