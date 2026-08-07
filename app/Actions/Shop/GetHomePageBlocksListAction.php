<?php

declare(strict_types=1);

namespace App\Actions\Shop;

use App\Data\Shop\HomePage\HomePageBlockListData;
use App\Models\HomePageBlock;
use Illuminate\Support\Collection;

final readonly class GetHomePageBlocksListAction
{
    /**
     * @return Collection<int, HomePageBlockListData>
     */
    public function handle(): Collection
    {
        return HomePageBlock::query()
            ->where('is_active', true)
            ->orderBy('location')
            ->orderBy('order')
            ->get()
            ->map(function (HomePageBlock $block): HomePageBlockListData {
                return new HomePageBlockListData(
                    id: $block->id,
                    type: $block->type,
                    location: $block->location,
                    order: $block->order,
                    preset: $block->content['preset'] ?? null
                );
            });
    }
}
