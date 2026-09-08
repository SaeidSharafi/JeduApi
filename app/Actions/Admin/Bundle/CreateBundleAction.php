<?php

declare(strict_types=1);

namespace App\Actions\Admin\Bundle;

use App\Actions\Admin\GetThumbnailUrlAction;
use App\Data\Admin\Bundle\BundleCreateData;
use App\Models\Bundle;
use Illuminate\Support\Facades\DB;

final readonly class CreateBundleAction
{
    public function __construct(private GetThumbnailUrlAction $thumbnailUrlAction) {}

    public function handle(BundleCreateData $data): Bundle
    {
        return DB::transaction(function () use ($data): Bundle {
            $mediaToAttach               = $data->media;
            $bundleData                  = $data->except('name', 'short_name', 'media')->toArray();
            $bundleData['thumbnail_url'] = $this->thumbnailUrlAction->handle($mediaToAttach);

            $bundle = Bundle::create([
                'full_name'  => $data->name,
                'short_name' => $data->short_name ?? $data->name,
                ...$bundleData,
            ]);

            foreach ($mediaToAttach as $tag => $mediaIds) {
                if (is_array($mediaIds)) {
                    foreach ($mediaIds as $mediaId) {
                        $bundle->attachMedia($mediaId, $tag);
                    }
                }
            }

            return $bundle;
        });
    }
}
