<?php

namespace App\Actions\Admin\CollaborationCarousel;

use App\Data\Admin\CollaborationCarousel\CollaborationCarouselCreateData;
use App\Models\CollaborationCarousel;
use Illuminate\Support\Facades\DB;
use Plank\Mediable\Media;

class CreateCollaborationCarouselAction
{
    public function handle(CollaborationCarouselCreateData $data): CollaborationCarousel
    {
       return DB::transaction(function () use ($data): CollaborationCarousel {
            $image = null;
            if ($data->image){
                $image = Media::find($data->image);
            }
            $carouselData = [
                ...$data->except('image')->toArray(),
                'image_url' => $image?->getUrl(),
                'image_alt' => $data->title,
            ];
            $carousel = CollaborationCarousel::query()->create($carouselData)->fresh();
            $carousel->syncMedia($image, 'image');
            $carousel->refresh();
            return $carousel;
        });
    }
}
