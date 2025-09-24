<?php

declare(strict_types=1);

namespace App\Actions\Admin\Seminar;

use App\Actions\Admin\GetThumbnailUrlAction;
use App\Data\Admin\Seminar\CreateSeminarData;
use App\Models\Seminar;

final class UpdateSeminarAction
{
    public function __construct(
        private GetThumbnailUrlAction $thumbnailUrlAction
    ) {}

    public function handle(CreateSeminarData $data, Seminar $seminar): void
    {
        $valdiatedData                  = $data->except('media', 'categories', 'digital_assets')->toArray();
        $valdiatedData['thumbnail_url'] = $this->thumbnailUrlAction->handle($data->media);
        $seminar->update($valdiatedData);
        $seminar->products()->update(['slug' => $data->slug]);
        $mediaToAttach       = $data->media          ?? [];
        $categoriesToAttach  = $data->categories     ?? [];
        $digitalAssetsAttach = $data->digital_assets ?? [];
        $seminar->categories()->sync($categoriesToAttach);
        $seminar->digitalAssets()->sync($digitalAssetsAttach);
        foreach (['gallery', 'video', 'cover'] as $tag) {
            $mediaIds = $mediaToAttach[$tag] ?? null;
            if (is_array($mediaIds)) {
                $seminar->syncMedia($mediaIds, $tag);
            }
        }
    }
}
