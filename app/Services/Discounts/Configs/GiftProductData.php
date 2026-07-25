<?php

declare(strict_types=1);

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

final class GiftProductData extends Data
{
    public function __construct(
        public int $product_delivery_option_id
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @codeCoverageIgnore
     */
    public static function rules(): array
    {
        return [
            'product_delivery_option_id' => ['required', 'integer', 'exists:product_delivery_options,id'],
        ];
    }

    /**
     * Extra frontend metadata for specific fields.
     *
     * @return array<string, array<string, mixed>>
     *
     * @codeCoverageIgnore
     */
    public static function fieldMeta(): array
    {
        return [
            'product_delivery_option_id' => [
                'model_reference' => [
                    'table'          => 'product_delivery_options',
                    'column'         => 'id',
                    'display_column' => 'name',
                ],
            ],
        ];
    }
}
