<?php

declare(strict_types=1);

namespace App\Data\Admin\Product;

use App\Enums\PublicationStatusEnum;
use App\Models\Product;
use App\Rules\PublishedProductExistRule;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class ProductUpdateData extends Data
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
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'term_id'   => ['required', 'integer', 'exists:terms,id'],
            'status'    => [
                'required',
                'string',
                Rule::enum(PublicationStatusEnum::class),
                new PublishedProductExistRule(
                    (request()->route()->parameter('product') instanceof Product)
                        ? request()->route()->parameter('product')
                        : null
                ),
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

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'vendor_id' => [
                'description' => 'ID of the vendor',
                'required'    => true,
                'example'     => 1,
            ],
            'term_id' => [
                'description' => 'ID of the term',
                'required'    => true,
                'example'     => 1,
            ],
            'status' => [
                'description' => 'Publication status of the product',
                'required'    => true,
                'example'     => 'published',
            ],
            'is_visible' => [
                'description' => 'Whether the product is visible',
                'required'    => true,
                'example'     => true,
            ],
            'short_description' => [
                'description' => 'Short description of the product',
                'required'    => false,
                'example'     => 'A short summary of the product',
            ],
            'short_name' => [
                'description' => 'Short name of the product',
                'required'    => false,
                'example'     => 'PRD-001',
            ],
            'name' => [
                'description' => 'Name of the product',
                'required'    => true,
                'example'     => 'Mathematics Course',
            ],
            'is_featured' => [
                'description' => 'Whether the product is featured',
                'required'    => true,
                'example'     => false,
            ],
            'categories' => [
                'description' => 'Array of category IDs',
                'required'    => true,
                'example'     => [1, 2],
            ],
            'details_json' => [
                'description' => 'Additional details for the product (structure may vary by productable_type)',
                'required'    => false,
                'example'     => ['key' => 'value'],
            ],
        ];
    }
}
