<?php

namespace App\Actions\Admin\CollaborationCarousel;

use App\Data\Admin\CollaborationCarousel\CollaborationCarouselCreateData;
use App\Models\CollaborationCarousel;
use Illuminate\Support\Facades\DB;
use Plank\Mediable\Media;

class UpdateCollaborationCarouselAction
{
    public function handle(CollaborationCarousel $carousel, CollaborationCarouselCreateData $data): CollaborationCarousel
    {
       return DB::transaction(function () use ($carousel, $data): CollaborationCarousel {
            $image = null;
            if ($data->image) {
                $image = Media::find($data->image);
            }
            $carouselData = [
                ...$data->except('image')->toArray(),
                'image_url' => $image ? $image?->getUrl() : null,
                'image_alt' => $image ? $data->title : null,
            ];
            $carousel->update($carouselData);
            $carousel->syncMedia($image, 'image');
            $carousel->refresh();
            return $carousel;
        });
    }
}
