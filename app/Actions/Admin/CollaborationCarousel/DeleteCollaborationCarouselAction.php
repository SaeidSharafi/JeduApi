<?php

namespace App\Actions\Admin\CollaborationCarousel;

use App\Models\CollaborationCarousel;
use Illuminate\Support\Facades\DB;
use Plank\Mediable\Media;

class DeleteCollaborationCarouselAction
{
    public function handle(CollaborationCarousel $carousel): void
    {
        DB::transaction(function () use ($carousel): void {
            $image = $carousel->getMedia('image')->first();
            $carousel->delete();
            $image?->delete();
        });
    }
}
