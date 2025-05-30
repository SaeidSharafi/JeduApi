<?php

declare(strict_types=1);

namespace App\Actions\Seminar;

use App\Data\Seminar\CreateSeminarData;
use App\Models\Seminar;

final class UpdateSeminarAction
{
    public function handle(CreateSeminarData $data, Seminar $seminar): void
    {
        $seminar->update($data->except('media', 'categories', 'digital_assets')->all());
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
