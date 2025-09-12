<?php

declare(strict_types=1);

namespace App\Actions\Admin\Setting;

use App\Data\Admin\Settings\HomePageBlock\HomePageBlockCreateData;
use App\Enums\HomePageBlockTypeEnum;
use App\Models\HomePageBlock;
use Illuminate\Support\Facades\DB;
use Plank\Mediable\Media;

class StoreHomePageBlockAction
{
    public function handle(HomePageBlockCreateData $data): HomePageBlock
    {
        return DB::transaction(function () use ($data) {

            if (isset($data->content['image_id'])) {
                $media = Media::findOrFail($data->content['image_id']);
                $data->content['image_url'] = $media->getUrl();
            }

            $block = HomePageBlock::create([
                'type' => $data->type,
                'title' => $data->title,
                'location' => $data->location,
                'order' => $data->order,
                'is_active' => $data->is_active,
                'content' => $data->content,
            ]);
            if (isset($media)) {
                $block->attachMedia($media, 'image');
            }

            return $block;
        });
    }
}
