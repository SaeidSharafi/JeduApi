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

    public static function rules(?ValidationContext $context = null): array
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

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'title' => [
                'description' => 'Title for the About Us page.',
                'example'     => 'About Jedu Academy',
            ],
            'main_block' => [
                'description' => 'Main block section.',
                'example'     => [
                    'title'   => 'Who We Are',
                    'content' => 'Jedu Academy is a leading provider of online education.',
                    'icon'    => 101,
                ],
            ],
            'main_block.title' => [
                'description' => 'Title of the main block.',
                'example'     => 'Who We Are',
            ],
            'main_block.content' => [
                'description' => 'Content of the main block.',
                'example'     => 'Jedu Academy is a leading provider of online education.',
            ],
            'main_block.icon' => [
                'description' => 'Media ID for the main block icon.',
                'example'     => 101,
            ],
            'images' => [
                'description' => 'Array of image media IDs.',
                'example'     => [201, 202],
            ],
            'images.*' => [
                'description' => 'Media ID for an image.',
                'example'     => 201,
            ],
            'active_course_groups_block' => [
                'description' => 'Active course groups block.',
                'example'     => [
                    'title'   => 'Active Courses',
                    'content' => 'We offer a variety of active courses.',
                    'icon'    => 102,
                ],
            ],
            'active_course_groups_block.title' => [
                'description' => 'Title of the active course groups block.',
                'example'     => 'Active Courses',
            ],
            'active_course_groups_block.content' => [
                'description' => 'Content of the active course groups block.',
                'example'     => 'We offer a variety of active courses.',
            ],
            'active_course_groups_block.icon' => [
                'description' => 'Media ID for the active course groups block icon.',
                'example'     => 102,
            ],
            'capabilities_block' => [
                'description' => 'Capabilities block section.',
                'example'     => [
                    'title'   => 'Our Capabilities',
                    'content' => 'We provide top-notch learning tools.',
                    'icon'    => 103,
                ],
            ],
            'capabilities_block.title' => [
                'description' => 'Title of the capabilities block.',
                'example'     => 'Our Capabilities',
            ],
            'capabilities_block.content' => [
                'description' => 'Content of the capabilities block.',
                'example'     => 'We provide top-notch learning tools.',
            ],
            'capabilities_block.icon' => [
                'description' => 'Media ID for the capabilities block icon.',
                'example'     => 103,
            ],
            'about_online_course_block_1' => [
                'description' => 'First online course block.',
                'example'     => [
                    'title'   => 'Online Course 1',
                    'content' => 'Description of online course 1.',
                    'icon'    => 104,
                ],
            ],
            'about_online_course_block_1.title' => [
                'description' => 'Title of the first online course block.',
                'example'     => 'Online Course 1',
            ],
            'about_online_course_block_1.content' => [
                'description' => 'Content of the first online course block.',
                'example'     => 'Description of online course 1.',
            ],
            'about_online_course_block_1.icon' => [
                'description' => 'Media ID for the first online course block icon.',
                'example'     => 104,
            ],
            'about_online_course_block_2' => [
                'description' => 'Second online course block.',
                'example'     => [
                    'title'   => 'Online Course 2',
                    'content' => 'Description of online course 2.',
                    'icon'    => 105,
                ],
            ],
            'about_online_course_block_2.title' => [
                'description' => 'Title of the second online course block.',
                'example'     => 'Online Course 2',
            ],
            'about_online_course_block_2.content' => [
                'description' => 'Content of the second online course block.',
                'example'     => 'Description of online course 2.',
            ],
            'about_online_course_block_2.icon' => [
                'description' => 'Media ID for the second online course block icon.',
                'example'     => 105,
            ],
        ];
    }
}
