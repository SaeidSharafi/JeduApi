<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings\HomePageBlock;

use App\Enums\HomePageBlockTypeEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class HomePageBlockCreateData extends Data
{
    public function __construct(
        public HomePageBlockTypeEnum $type,
        public string $title,
        public string $location,
        public int $order,
        public bool $is_active,
        public array $content,
    ) {
    }

    public static function rules(?ValidationContext $context = null): array
    {
        $rules = [
            'type'      => ['required', 'string', Rule::enum(HomePageBlockTypeEnum::class)],
            'title'     => ['required', 'string', 'max:255'],
            'location'  => ['required', 'string'],
            'order'     => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'content'   => ['required', 'array'],
        ];
        $content = [];
        switch (request()->input('type')) {
            case HomePageBlockTypeEnum::MAIN_CATEGORIES->value:
            case HomePageBlockTypeEnum::CURATED_LIST->value:
                $content = CuratedListBlockContentData::rules();
                break;
            case HomePageBlockTypeEnum::BANNER->value:
                $content = BannerBlockContentData::rules();
                break;

            case HomePageBlockTypeEnum::WEBINAR_BANNER->value:
                $content = WebinarBannerBlockContentData::rules();
                break;
        }
        foreach ($content as $key => $value) {
            $rules["content.$key"] = $value;
        }
        return $rules;
    }


    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'type'      => [
                'description' => 'The type of the home page block.'
                    . ' Possible values: ' . implode(', ', HomePageBlockTypeEnum::getAllValues()),
                'example'     => HomePageBlockTypeEnum::BANNER->value,
            ],
            'title'     => ['description' => 'The title of the home page block.'],
            'location'  => ['description' => 'The location where the block will be displayed.'],
            'order'     => ['description' => 'The order of the block in the specified location.'],
            'is_active' => ['description' => 'Whether the block is active or not.'],
            'content'   => ['description' => 'The content of the home page block. The structure of this field varies based on the block type. See below for details.'],
            // if type is curated list or Curated Item List (title, item ids, type)
            'content.item_ids' => ['description' => 'An array of item IDs (product or category) to be displayed in the curated list. Required for Curated List and Main Categories type.'],
            // if type is Banner
            'content.image_id' => ['description' => 'The ID of the image media. Required for Banner and Webinar Banner types.'],
            'content.image_url' => ['description' => 'The URL of the image media. This field is auto-populated based on the provided image_id.'],
            'content.action' => ['description' => 'The action URL or link associated with the banner. Required for Banner type.'],
            'content.action_title' => ['description' => 'The title of the action button or link. Required for Banner type.'],
            'content.content' => ['description' => 'Optional textual content or description for the banner.'],
            'content.preset' => ['description' => 'Optional preset style for the banner.'],
            // if type is Webinar Banner
            'content.product_id' => ['description' => 'The ID of the product associated with the webinar banner. Required for Webinar Banner type.'],
            'content.text' => ['description' => 'The text to be displayed on the webinar banner. Required for Webinar Banner type.']
        ];
    }
}
