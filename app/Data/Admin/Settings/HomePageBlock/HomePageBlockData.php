<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings\HomePageBlock;

use App\Enums\Content\HomePageBlockTypeEnum;
use App\Models\HomePageBlock;
use Spatie\LaravelData\Data;

final class HomePageBlockData extends Data
{
    public function __construct(
        public int $id,
        public string $type,
        public string $title,
        public string $location,
        public int $order,
        public bool $is_active,
        public BannerBlockContentData|CuratedListBlockContentData|WebinarBannerBlockContentData|DynamicListBlockContentData|null $content,
    ) {}

    public static function fromModel(HomePageBlock $block): self
    {
        $content = match ($block->type) {
            HomePageBlockTypeEnum::BANNER => new BannerBlockContentData(
                image_id: $block->content['image_id']         ?? null,
                image_url: $block->content['image_url']       ?? null,
                action: $block->content['action']             ?? '',
                action_title: $block->content['action_title'] ?? '',
                content: $block->content['content']           ?? null,
                preset: $block->content['preset']             ?? null,
            ),
            HomePageBlockTypeEnum::MAIN_CATEGORIES, HomePageBlockTypeEnum::CURATED_LIST => new CuratedListBlockContentData(
                items: $block->content['items']   ?? [],
                preset: $block->content['preset'] ?? 'default',
            ),
            HomePageBlockTypeEnum::WEBINAR_BANNER => new WebinarBannerBlockContentData(
                product_id: $block->content['product_id'] ?? null,
                text: $block->content['text']             ?? '',
                image_id: $block->content['image_id']     ?? null,
                image_url: $block->content['image_url']   ?? null,
            ),
            HomePageBlockTypeEnum::DYNAMIC_LIST => new DynamicListBlockContentData(
                entity_type: \App\Enums\Content\DynamicListEntityTypeEnum::from($block->content['entity_type'] ?? 'product'),
                sort_by: \App\Enums\Content\DynamicListSortByEnum::from($block->content['sort_by'] ?? 'created_at:desc'),
                limit: $block->content['limit']               ?? 10,
                preset: $block->content['preset']             ?? 'default',
                category_ids: $block->content['category_ids'] ?? null,
            ),
            // @codeCoverageIgnoreStart
            default => null,
            // @codeCoverageIgnoreEnd
        };

        return new self(
            id: $block->id,
            type: $block->type->value,
            title: $block->title,
            location: $block->location,
            order: $block->order,
            is_active: $block->is_active,
            content: $content,
        );
    }
}
