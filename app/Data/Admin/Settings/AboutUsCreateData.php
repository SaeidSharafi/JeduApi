<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings;

use App\Data\ArticleSectionCreateData;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class AboutUsCreateData extends Data
{
    public function __construct(
        public string $title,
        public ArticleSectionCreateData $main_block,
        public array $images,
        public ArticleSectionCreateData $active_course_groups_block,
        public ArticleSectionCreateData $capabilities_block,
        public ArticleSectionCreateData $about_online_course_block_1,
        public ArticleSectionCreateData $about_online_course_block_2,
    ) {}

    public static function rules(ValidationContext $context): array
    {
        return [
            'title'                               => ['required', 'string', 'max:255'],
            'main_block'                          => ['required', 'array'],
            'main_block.title'                    => ['required', 'string', 'max:255'],
            'main_block.content'                  => ['required', 'string'],
            'main_block.icon'                     => ['nullable', 'integer', 'exists:media,id'],
            'images'                              => ['sometimes', 'array'],
            'images.*'                            => ['nullable', 'integer', 'exists:media,id'],
            'active_course_groups_block'          => ['required', 'array'],
            'active_course_groups_block.title'    => ['required', 'string', 'max:255'],
            'active_course_groups_block.content'  => ['required', 'string'],
            'active_course_groups_block.icon'     => ['nullable', 'integer', 'exists:media,id'],
            'capabilities_block'                  => ['required', 'array'],
            'capabilities_block.title'            => ['required', 'string', 'max:255'],
            'capabilities_block.content'          => ['required', 'string'],
            'capabilities_block.icon'             => ['nullable', 'integer', 'exists:media,id'],
            'about_online_course_block_1'         => ['required', 'array'],
            'about_online_course_block_1.title'   => ['required', 'string', 'max:255'],
            'about_online_course_block_1.content' => ['required', 'string'],
            'about_online_course_block_1.icon'    => ['nullable', 'integer', 'exists:media,id'],
            'about_online_course_block_2'         => ['required', 'array'],
            'about_online_course_block_2.title'   => ['required', 'string', 'max:255'],
            'about_online_course_block_2.content' => ['required', 'string'],
            'about_online_course_block_2.icon'    => ['nullable', 'integer', 'exists:media,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(...$args): array
    {
        return [
            'title'                               => __('validation.attributes.about_us.title'),
            'main_block.title'                    => __('validation.attributes.about_us.main_block_title'),
            'main_block.content'                  => __('validation.attributes.about_us.main_block_content'),
            'main_block.icon'                     => __('validation.attributes.about_us.main_block_image'),
            'images'                              => __('validation.attributes.about_us.icons'),
            'active_course_groups_block.title'    => __('validation.attributes.about_us.active_course_groups_title'),
            'active_course_groups_block.content'  => __('validation.attributes.about_us.active_course_groups_content'),
            'active_course_groups_block.icon'     => __('validation.attributes.about_us.active_course_groups_image'),
            'capabilities_block.title'            => __('validation.attributes.about_us.capabilities_title'),
            'capabilities_block.content'          => __('validation.attributes.about_us.capabilities_content'),
            'capabilities_block.icon'             => __('validation.attributes.about_us.capabilities_image'),
            'about_online_course_block_1.title'   => __('validation.attributes.about_us.online_course_1_title'),
            'about_online_course_block_1.content' => __('validation.attributes.about_us.online_course_1_content'),
            'about_online_course_block_1.icon'    => __('validation.attributes.about_us.online_course_1_image'),
            'about_online_course_block_2.title'   => __('validation.attributes.about_us.online_course_2_title'),
            'about_online_course_block_2.content' => __('validation.attributes.about_us.online_course_2_content'),
            'about_online_course_block_2.icon'    => __('validation.attributes.about_us.online_course_2_image'),
        ];
    }
}
