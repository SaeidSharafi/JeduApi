<?php

namespace App\Actions\Admin;

use App\Enums\MediaTagEnum;

class GetThumnailUrlAction
{
    public function handle(array $media): ?string
    {
        $thumbnail = data_get($media, MediaTagEnum::COVER->value.'.0');
        if (!$thumbnail) {
            return null;
        }
        return \Plank\Mediable\Media::find($thumbnail)?->getUrl() ?? null;
    }
}
