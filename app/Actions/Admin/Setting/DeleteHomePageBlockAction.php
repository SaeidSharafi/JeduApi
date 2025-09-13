<?php

declare(strict_types=1);

namespace App\Actions\Admin\Setting;

use App\Data\Admin\Settings\HomePageBlock\HomePageBlockCreateData;
use App\Enums\HomePageBlockTypeEnum;
use App\Models\HomePageBlock;
use Illuminate\Support\Facades\DB;
use Plank\Mediable\Media;

class DeleteHomePageBlockAction
{
    public function handle(HomePageBlock $block): void
    {
        DB::transaction(function () use ($block) {
            $block->media()->delete();
            $block->delete();
        });
    }
}
