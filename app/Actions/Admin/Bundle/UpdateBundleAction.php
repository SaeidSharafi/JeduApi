<?php

declare(strict_types=1);

namespace App\Actions\Admin\Bundle;

use App\Actions\Admin\GetThumbnailUrlAction;
use App\Data\Admin\Bundle\BundleUpdateData;
use App\Enums\MediaTagEnum;
use App\Models\Bundle;
use Illuminate\Support\Facades\DB;

final readonly class UpdateBundleAction
{
    public function __construct(private GetThumbnailUrlAction $thumbnailUrlAction) {}

    public function handle(BundleUpdateData $data, Bundle $bundle): Bundle
    {
        return DB::transaction(function () use ($data, $bundle): Bundle {
            $bundleData                  = $data->except('name', 'short_name', 'media')->toArray();
            $bundleData['thumbnail_url'] = $this->thumbnailUrlAction->handle($data->media);
            $bundle->update([
                'full_name'  => $data->name,
                'short_name' => $data->short_name ?? $data->name,
                ...$bundleData,
            ]);

            foreach (MediaTagEnum::getAllValues() as $tag) {
                $mediaIds = $data->media[$tag] ?? null;
                if (is_array($mediaIds)) {
                    $bundle->syncMedia($mediaIds, $tag);
                }
            }

            return $bundle->fresh();
        });
    }
}
