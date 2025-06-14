<?php

namespace App\Data\Product;

use App\Contracts\ProductableContract;
use App\Contracts\ProductableDataContract;
use App\Data\Casts\MorphEnumCast;
use App\Data\Casts\ProductableCast;
use App\Data\Term\ShowTermData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\MorphTypeEnum;
use App\Enums\ProductableEnum;
use App\Enums\PublicationStatusEnum;
use App\Models\Product;
use App\Rules\ProductableExistRule;
use App\Rules\PublishedProductExistRule;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class ProductUpdateData extends Data
{
    public function __construct(
        public int $vendor_id,
        public int $term_id,
        public string $status,
        public bool $is_visible,
        public ?string $short_description,
        public ?string $short_name,
        public ?string $name,
        public bool $is_featured,
        public array $categories,
        public ?array $details_json
    ) {
    }

    public static function rules(ValidationContext $context): array
    {
        return [
            'vendor_id'         => ['required', 'integer', 'exists:vendors,id'],
            'term_id'              => ['required', 'integer', 'exists:terms,id'],
            'status'            => [
                'required',
                'string',
                Rule::enum(PublicationStatusEnum::class),
                new PublishedProductExistRule(
                    (request()->route()->parameter('product') instanceof Product)
                        ? request()->route()->parameter('product')
                        : null
                )
            ],
            'is_visible'        => ['required', 'boolean'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'short_name'        => ['nullable', 'string', 'max:255'],
            'name'              => ['required', 'string', 'max:255'],
            'is_featured'       => ['required', 'boolean'],
            'categories'        => ['required', 'array'],
            'categories.*'      => ['required', 'integer', 'exists:categories,id'],
            'details_json'      => ['nullable', 'array'],
        ];
    }
}
