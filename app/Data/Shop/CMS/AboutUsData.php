<?php

declare(strict_types=1);

namespace App\Data\Shop\CMS;

use Spatie\LaravelData\Data;

final class AboutUsData extends Data
{
    public function __construct(
        public string $title,
        public ShopArticleSectionData $main_block,
        public array $images,
        public ShopArticleSectionData $active_course_groups_block,
        public ShopArticleSectionData $capabilities_block,
        public ShopArticleSectionData $about_online_course_block_1,
        public ShopArticleSectionData $about_online_course_block_2,
    ) {}

    /**
     * Create AboutUsData from Admin AboutUsData.
     */
    public static function fromSetting(array $setting): self
    {

        return new self(
            title: $setting['title'],
            main_block: new ShopArticleSectionData(
                title: data_get($setting, 'main_block.title'),
                content: data_get($setting, 'main_block.content'),
                icon_url: data_get($setting, 'main_block.icon.url'),
                subtitle: data_get($setting, 'main_block.subtitle'),
            ),
            images: array_map(fn ($image) => data_get($image, 'url'), data_get($setting, 'images')),
            active_course_groups_block: new ShopArticleSectionData(
                title: data_get($setting, 'active_course_groups_block.title'),
                content: data_get($setting, 'active_course_groups_block.content'),
                icon_url: data_get($setting, 'active_course_groups_block.icon.url'),
                subtitle: data_get($setting, 'active_course_groups_block.subtitle'),
            ),
            capabilities_block: new ShopArticleSectionData(
                title: data_get($setting, 'capabilities_block.title'),
                content: data_get($setting, 'capabilities_block.content'),
                icon_url: data_get($setting, 'capabilities_block.icon.url'),
                subtitle: data_get($setting, 'capabilities_block.subtitle'),
            ),
            about_online_course_block_1: new ShopArticleSectionData(
                title: data_get($setting, 'about_online_course_block_1.title'),
                content: data_get($setting, 'about_online_course_block_1.content'),
                icon_url: data_get($setting, 'about_online_course_block_1.icon.url'),
                subtitle: data_get($setting, 'about_online_course_block_1.subtitle'),
            ),
            about_online_course_block_2: new ShopArticleSectionData(
                title: data_get($setting, 'about_online_course_block_2.title'),
                content: data_get($setting, 'about_online_course_block_2.content'),
                icon_url: data_get($setting, 'about_online_course_block_2.icon.url'),
                subtitle: data_get($setting,'about_online_course_block_2.subtitle'),
            ),
        );
    }
}
