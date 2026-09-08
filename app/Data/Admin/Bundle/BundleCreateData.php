<?php

declare(strict_types=1);

namespace App\Data\Admin\Bundle;

use App\Enums\Content\PublicationStatusEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

final class BundleCreateData extends Data
{
    public function __construct(
        public string $name,
        public string $slug,
        public string $description,
        public string $status = 'draft',
        public ?string $short_name = null,
        public ?string $thumbnail_url = null,
        public ?array $properties = null,
        public ?array $additional_info = null,
        public ?array $faq = null,
        public array $media = [],
    ) {}

    public static function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'slug'            => ['required', 'alpha_dash', 'max:255', 'unique:bundles,slug'],
            'description'     => ['required', 'string'],
            'status'          => ['required', Rule::enum(PublicationStatusEnum::class)],
            'short_name'      => ['nullable', 'string', 'max:255'],
            'thumbnail_url'   => ['nullable', 'string', 'max:255'],
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
        ];
    }
}
