<?php

declare(strict_types=1);

namespace App\Data\Admin\Product;

use App\Enums\Product\RelationTypeEnum;
use Spatie\LaravelData\Data;

final class RelatedProductSyncData extends Data
{
    public function __construct(
        /** @var array<int> */
        public array $product_ids,
        public RelationTypeEnum $relation_type
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'product_ids'   => ['required', 'array', 'min:1'],
            'product_ids.*' => ['required', 'integer', 'exists:products,id'],
            'relation_type' => ['required', 'string', 'in:related,cross_sell,upsell'],
        ];
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, mixed>
     */
    public static function bodyParameters(): array
    {
        return [
            'product_ids' => [
                'description' => 'Array of product IDs to relate to the main product.',
                'example'     => [2, 3, 4],
            ],
            'relation_type' => [
                'description' => 'Type of relation. Possible values: related, cross_sell, upsell.',
                'example'     => 'related',
            ],
        ];
    }
}
