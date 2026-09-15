<?php

declare(strict_types=1);

namespace App\Data\Admin\Bundle;

use App\Enums\Content\PublicationStatusEnum;
use App\Traits\ValidatesMetaTags;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

final class BundleCreateData extends Data
{
    use ValidatesMetaTags;

    public function __construct(
        public string $full_name,
        public string $slug,
        public string $description,
        public string $status = 'draft',
        public ?string $short_name = null,
        public ?string $thumbnail_url = null,
        public ?string $meta_title = null,
        public ?string $meta_description = null,
        public ?string $meta_keywords = null,
        public ?array $properties = null,
        public ?array $additional_info = null,
        public ?array $faq = null,
        public array $media = [],
    ) {}

    public static function rules(): array
    {
        return array_merge([
            'full_name'       => ['required', 'string', 'max:255'],
            'slug'            => ['required', 'alpha_dash', 'max:255', 'unique:bundles,slug'],
            'description'     => ['required', 'string'],
            'status'          => ['required', Rule::enum(PublicationStatusEnum::class)],
            'short_name'      => ['nullable', 'string', 'max:255'],
            'properties'      => ['nullable', 'array'],
            'additional_info' => ['nullable', 'array'],
            'faq'             => ['nullable', 'array'],
            'media'           => ['required', 'array'],
            'media.gallery'   => ['nullable', 'array'],
            'media.cover'     => ['required', 'array'],
            'media.video'     => ['nullable', 'array'],
            'media.cover.*'   => ['required', 'integer', 'exists:media,id'],
            'media.gallery.*' => ['nullable', 'integer', 'exists:media,id'],
            'media.video.*'   => ['nullable', 'integer', 'exists:media,id'],
        ], self::metaTagValidationRules());
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'full_name' => [
                'description' => 'The bundle name.',
                'example'     => 'Full Stack Package',
            ],
            'slug' => [
                'description' => 'A unique URL-safe slug for the bundle.',
                'example'     => 'full-stack-package',
            ],
            'description' => [
                'description' => 'The bundle description.',
                'example'     => 'A bundle of full stack courses.',
            ],
            'status' => [
                'description' => 'Publication status.',
                'example'     => PublicationStatusEnum::PUBLISHED->value,
            ],
            'short_name' => [
                'description' => 'A short display name.',
                'example'     => 'FULLSTACK',
            ],
            'properties' => [
                'description' => 'Additional custom properties as a list of key/value pairs.',
                'example'     => [['key' => 'duration', 'value' => '3 months']],
            ],
            'additional_info' => [
                'description' => 'Additional information as a list of title/value pairs.',
                'example'     => [['title' => 'Includes', 'value' => '12 courses']],
            ],
            'faq' => [
                'description' => 'List of FAQ items.',
                'example'     => [['question' => 'Refunds?', 'answer' => 'Within 7 days']],
            ],
            'media' => [
                'description' => 'Media object containing cover, gallery, and video.',
                'example'     => [
                    'cover'   => [1],
                    'gallery' => [2, 3],
                    'video'   => [4],
                ],
            ],
            'media.cover' => [
                'description' => 'Array of cover media IDs.',
                'example'     => [1],
            ],
            'media.gallery' => [
                'description' => 'Array of gallery media IDs.',
                'example'     => [2, 3],
            ],
            'media.video' => [
                'description' => 'Array of video media IDs.',
                'example'     => [4],
            ],
            'media.cover.*' => [
                'description' => 'A cover media ID.',
                'example'     => 1,
            ],
            'media.gallery.*' => [
                'description' => 'A gallery media ID.',
                'example'     => 2,
            ],
            'media.video.*' => [
                'description' => 'A video media ID.',
                'example'     => 4,
            ],
        ] + self::metaTagBodyParameters();
    }
}
