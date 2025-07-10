<?php

declare(strict_types=1);

namespace App\Actions\Seminar;

use App\Data\Admin\Seminar\CreateSeminarData;
use App\Models\Seminar;

final class CreateSeminarAction
{
    public function handle(CreateSeminarData $data): void
    {
        $mediaToAttach       = $data->media          ?? [];
        $categoriesToAttach  = $data->categories     ?? [];
        $digitalAssetsAttach = $data->digital_assets ?? [];
        $seminar             = Seminar::query()->create($data->except('media', 'categories', 'digital_assets')->toArray());
        $seminar->categories()->attach($categoriesToAttach);
        $seminar->digitalAssets()->attach($digitalAssetsAttach);
        foreach ($mediaToAttach as $tag => $mediaIds) {
            $seminar->attachMedia($mediaIds, $tag);
        }
    }
}
