<?php

declare(strict_types=1);

namespace App\Data\Shop\CMS;

use App\Data\Admin\MediaData;
use App\Data\ArticleSectionData;
use Spatie\LaravelData\Data;

final class CollaborationPageData extends Data
{
    public function __construct(
        public string $title,
        public string $content,
        public ?string $image,

    ) {
    }

    /**
     * Get default about us data for seeding.
     */
    public static function fromSetting(array $setting): CollaborationPageData
    {
        return new self(
            title: data_get($setting,'title'),
            content: data_get($setting,'content'),
            image: data_get($setting,'image.url'),
        );
    }
}
