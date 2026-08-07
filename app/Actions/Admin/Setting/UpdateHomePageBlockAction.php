<?php

declare(strict_types=1);

namespace App\Actions\Admin\Setting;

use App\Data\Admin\Settings\HomePageBlock\HomePageBlockCreateData;
use App\Models\HomePageBlock;
use Illuminate\Support\Facades\DB;
use Plank\Mediable\Media;

final class UpdateHomePageBlockAction
{
    public function handle(HomePageBlock $block, HomePageBlockCreateData $data): HomePageBlock
    {
        return DB::transaction(function () use ($data, $block): HomePageBlock {

            if (isset($data->content['image_id'])) {
                $media                      = Media::findOrFail($data->content['image_id']);
                $data->content['image_url'] = $media->getUrl();
            }

            $block->update([
                'type'      => $data->type,
                'title'     => $data->title,
                'location'  => $data->location,
                'order'     => $data->order,
                'is_active' => $data->is_active,
                'content'   => $data->content,
            ]);
            if (isset($media)) {
                $block->syncMedia($media, 'image');
            }
            $block->refresh();

            return $block;
        });
    }
}
