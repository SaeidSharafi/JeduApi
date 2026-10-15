<?php

declare(strict_types=1);

namespace App\Actions\Admin\Setting;

use App\Enums\System\StorefrontTag;
use App\Events\StorefrontCacheInvalidated;
use App\Models\HomePageBlock;
use Illuminate\Support\Facades\DB;

final class DeleteHomePageBlockAction
{
    public function handle(HomePageBlock $block): void
    {
        DB::transaction(function () use ($block): void {
            $block->media()->delete();
            $block->delete();
        });

        StorefrontCacheInvalidated::dispatch(StorefrontTag::HomeBlocks);
    }
}
