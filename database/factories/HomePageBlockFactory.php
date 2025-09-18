<?php

namespace Database\Factories;

use App\Enums\DynamicListEntityTypeEnum;
use App\Enums\DynamicListSortByEnum;
use App\Enums\HomePageBlockTypeEnum;
use App\Models\HomePageBlock;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Plank\Mediable\Media;

/** @mixin Factory<HomePageBlock> */
class HomePageBlockFactory extends Factory
{
    protected $model = HomePageBlock::class;

    public function definition(): array
    {
        return [
            'type'       => $this->faker->randomElement(HomePageBlockTypeEnum::getAllValues()),
            'title'      => $this->faker->word(),
            'location'   => $this->faker->word(),
            'content'    => null,
            'order'      => $this->faker->randomNumber(),
            'is_active'  => $this->faker->boolean(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }

    public function banner(?Media $image = null): static
    {
        return $this->state(fn(array $attributes) => [
            'type'    => HomePageBlockTypeEnum::BANNER,
            'content' => [
                'action'      => 'https://example.com',
                'action_title'=> 'Click Here',
                'content'     => 'This is a banner',
                'preset'      => 'default',
                'image_id'    => $image?->id,
                'image_url'   => $image?->getUrl(),
            ],
        ]);
    }

    public function curatedList(array $itemsIds = [],HomePageBlockTypeEnum $typeEnum = HomePageBlockTypeEnum::CURATED_LIST): static
    {
        if ($typeEnum !== HomePageBlockTypeEnum::CURATED_LIST && $typeEnum !== HomePageBlockTypeEnum::MAIN_CATEGORIES) {
            throw new \InvalidArgumentException('Type must be CURATED_LIST or MAIN_CATEGORIES');
        }
        return $this->state(fn(array $attributes) => [
            'type'    => $typeEnum,
            'content' => [
                'items'  => $itemsIds ?: [1, 2, 3],
                'preset' => 'default',
            ]
        ]);
    }

    public function webinarBanner(?Media $image = null, ?int $productId = null): static
    {
        return $this->state(fn(array $attributes) => [
            'type'    => HomePageBlockTypeEnum::WEBINAR_BANNER,
            'content' => [
                'product_id' => $productId,
                'text'       => $this->faker->persianText(50),
                'image_id'   => $image?->id,
                'image_url'  => $image?->getUrl(),
            ],
        ]);
    }

    public function dynamicList(DynamicListEntityTypeEnum $entityType = DynamicListEntityTypeEnum::ALL_PRODUCTS,
        DynamicListSortByEnum $sortBy = DynamicListSortByEnum::CREATED_AT_DESC,
        int $limit = 10,
        ?array $categoryIds = null): static
    {
        return $this->state(fn(array $attributes) => [
            'type'    => HomePageBlockTypeEnum::DYNAMIC_LIST,
            'content' => [
                'entity_type'  => $entityType->value,
                'sort_by'      => $sortBy->value,
                'limit'        => $limit,
                'preset'       => 'default',
                'category_ids' => $categoryIds,
            ],
        ]);
    }


}
