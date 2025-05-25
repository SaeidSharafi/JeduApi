<?php

declare(strict_types=1);

namespace App\Data\File;

use App\Enums\PublicationStatusEnum;
use App\Traits\ValidatesMetaTags;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class CreateDigitalAssetData extends Data
{
    use ValidatesMetaTags;

    public function __construct(
        public string $name,
        public string $slug,
        public ?string $description,
        public ?string $version,
        public bool $is_attachable_to_course,
        #[WithCast(EnumCast::class)]
        public PublicationStatusEnum $status,
        public ?int $created_by,
        public ?string $keywords,
        public ?string $meta_title,
        public ?string $meta_description,
        public ?string $meta_keywords,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d H:i:s')]
        public ?Carbon $published_at,
        public ?int $page_count,
        public ?int $duration_seconds,
        public array $categories = [],
        public array $attachments = []
    ) {}

    public static function rules(): array
    {

        return array_merge(
            [
                'name' => ['required', 'string', 'max:255'],
                'slug' => [
                    'required',
                    'string',
                    'alpha_dash',
                    'max:191',
                    Rule::unique('digital_assets', 'slug')->where(function (Builder $query) {
                        $asset = request()->route()->parameter('digital_asset');
                        if ($asset && $asset->id) {
                            $query->whereNot('id', $asset->id);
                        }

                        return $query;
                    }),
                ],
                'description'             => ['nullable', 'string'],
                'version'                 => ['nullable', 'string', 'max:50'],
                'is_attachable_to_course' => ['nullable', 'boolean'],
                'status'                  => ['required', Rule::enum(PublicationStatusEnum::class)],
                'created_by'              => ['nullable', 'integer', 'exists:admins,id'],
                'keywords'                => ['nullable', 'string', 'max:255'],
                'published_at'            => ['nullable', 'date:Y-m-d H:i:s'],
                'page_count'              => ['nullable', 'integer', 'min:0'],
                'duration_seconds'        => ['nullable', 'integer', 'min:0'],
                'categories'              => ['required', 'array'],
                'categories.*'            => ['required', 'integer', 'exists:categories,id'],
                'attachments'             => ['array'],
                'attachments.main'        => ['required', 'integer', 'exists:media,id'],
                'attachments.preview'     => ['nullable', 'integer', 'exists:media,id'],
            ],
            self::metaTagValidationRules()
        );
    }

    /**
     * @codeCoverageIgnore
     *
     * Get the validation rules for the data.
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'The name of the digital asset.',
                'example'     => 'Digital Asset Name',
            ],
            'slug' => [
                'description' => 'A unique slug for the digital asset, used in URLs.',
                'example'     => 'digital-asset-name',
            ],
            'description' => [
                'description' => 'A brief description of the digital asset.',
                'example'     => 'This is a description of the digital asset.',
            ],
            'version' => [
                'description' => 'The version of the digital asset.',
                'example'     => '1.0.0',
            ],
            'is_attachable_to_course' => [
                'description' => 'Indicates if this asset can be attached to a course.',
                'example'     => true,
            ],
            'status' => [
                'description' => 'The publication status of the digital asset.',
                'example'     => PublicationStatusEnum::PUBLISHED->value,
            ],
            'page_count' => [
                'description' => 'The number of pages in the digital asset, if applicable.',
                'example'     => 100,
            ],
            'duration_seconds' => [
                'description' => 'The duration of the digital asset in seconds, if applicable.',
                'example'     => 3600,
            ],
            'created_by' => [
                'description' => 'The ID of the admin who created this digital asset.',
                'example'     => 1,
            ],
            'published_at' => [
                'description' => 'The date and time when the digital asset was published.',
                'example'     => '2023-10-01 12:00:00',
            ],
            'keywords' => [
                'description' => 'Keywords associated with the digital asset for search optimization.',
                'example'     => 'keyword1, keyword2',
            ],
            'meta_title' => [
                'description' => 'The meta title for the digital asset, used for SEO.',
                'example'     => 'Digital Asset Meta Title',
            ],
            'meta_description' => [
                'description' => 'The meta description for the digital asset, used for SEO.',
                'example'     => 'This is a meta description for the digital asset.',
            ],
            'meta_keywords' => [
                'description' => 'Meta keywords for the digital asset, used for SEO.',
                'example'     => 'meta keyword1, meta keyword2',
            ],
            'categories' => [
                'description' => 'An array of category IDs to which the digital asset belongs.',
                'example'     => [1, 2, 3],
            ],
            'categories.*' => [
                'description' => 'An array of category IDs to which the digital asset belongs.',
                'example'     => 1,
            ],
            'attachments.main' => [
                'description' => 'The main attachment for the digital asset, typically a file ID.',
                'example'     => 1,
            ],
            'attachments.preview' => [
                'description' => 'An optional preview attachment for the digital asset, typically a file ID.',
                'example'     => 2,
            ],
        ];
    }
}
