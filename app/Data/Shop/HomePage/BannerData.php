<?php

namespace App\Data\Shop\HomePage;

use Spatie\LaravelData\Data;

class BannerData extends Data
{
    //image_url
    //action
    //action_title
    //content
    //preset
    public function __construct(
        public ?string $image_url = null,
        public ?string $action = null,
        public ?string $action_title = null,
        public ?string $content = null,
        public ?string $preset = 'default',
    )
    {
    }
}
