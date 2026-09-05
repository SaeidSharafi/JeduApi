<?php

declare(strict_types=1);

namespace App\Data\Admin\Bundle;

use App\Enums\Content\PublicationStatusEnum;
use App\Models\Bundle;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

final class BundleUpdateData extends Data
{
    public function __construct(
        public string $name,
        public string $description,
        public string $status = 'draft',
        public ?string $short_name = null,
        public ?string $thumbnail_url = null,
        public ?array $properties = null,
        public ?array $additional_info = null,
        public ?array $faq = null,
    ) {}

    public static function rules(): array
    {
        $bundle = request()->route('bundle');
        $id     = $bundle instanceof Bundle ? $bundle->id : null;

        return [
            'name'            => ['required', 'string', 'max:255'],
            'description'     => ['required', 'string'],
            'status'          => ['required', Rule::enum(PublicationStatusEnum::class)],
            'short_name'      => ['nullable', 'string', 'max:255'],
            'thumbnail_url'   => ['nullable', 'string', 'max:255'],
            'properties'      => ['nullable', 'array'],
            'additional_info' => ['nullable', 'array'],
            'faq'             => ['nullable', 'array'],
            'slug'            => ['sometimes', 'alpha_dash', 'unique:bundles,slug,'.$id],
        ];
    }
}
