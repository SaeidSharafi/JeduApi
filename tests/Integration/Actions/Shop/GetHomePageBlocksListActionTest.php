<?php

declare(strict_types=1);

use App\Actions\Shop\GetHomePageBlocksListAction;
use App\Data\Shop\HomePage\HomePageBlockListData;
use App\Models\HomePageBlock;

it('returns collection of HomePageBlockListData for active blocks only', function (): void {
    // Create active and inactive blocks
    $activeBlock1 = HomePageBlock::factory()->banner()->create([
        'location'  => 'hero',
        'is_active' => true,
        'order'     => 2,
    ]);

    $activeBlock2 = HomePageBlock::factory()->curatedList([], App\Enums\Content\HomePageBlockTypeEnum::CURATED_LIST)->create([
        'location'  => 'main_content',
        'is_active' => true,
        'order'     => 1,
    ]);

    HomePageBlock::factory()->banner()->create([
        'location'  => 'hero',
        'is_active' => false,
    ]);

    $action = new GetHomePageBlocksListAction();
    $result = $action->handle();

    expect($result)->toHaveCount(2)
        ->and($result->first())->toBeInstanceOf(HomePageBlockListData::class);

    // Check the ordering: hero comes before main_content, then order by order field
    $blocks = $result->values();
    expect($blocks[0]->location)->toBe('hero')
        ->and($blocks[0]->id)->toBe($activeBlock1->id)
        ->and($blocks[1]->location)->toBe('main_content')
        ->and($blocks[1]->id)->toBe($activeBlock2->id);
});

it('extracts preset from block content', function (): void {
    $block = HomePageBlock::factory()->curatedList(
        [],
        App\Enums\Content\HomePageBlockTypeEnum::CURATED_LIST
    )->create([
        'is_active' => true,
        'content'   => [
            'items'  => [],
            'preset' => 'custom_preset',
        ],
    ]);

    $action = new GetHomePageBlocksListAction();
    $result = $action->handle();

    expect($result->first()->preset)->toBe('custom_preset');
});

it('returns null preset when not present in content', function (): void {
    $block = HomePageBlock::factory()->banner()->create([
        'is_active' => true,
        'content'   => [
            'title' => 'Banner Title',
        ],
    ]);

    $action = new GetHomePageBlocksListAction();
    $result = $action->handle();

    expect($result->first()->preset)->toBeNull();
});
