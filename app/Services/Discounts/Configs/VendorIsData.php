<?php

declare(strict_types=1);

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

final class VendorIsData extends Data
{
    /**
     * @param  array<int, int>  $vendor_ids
     */
    public function __construct(
        public array $vendor_ids
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @codeCoverageIgnore
     */
    public static function rules(): array
    {
        return [
            'vendor_ids'   => ['required', 'array'],
            'vendor_ids.*' => ['integer', 'exists:vendors,id'],
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
            'vendor_ids' => [
                'item' => [
                    'item_type'       => 'model',
                    'model_reference' => [
                        'table'          => 'vendors',
                        'column'         => 'id',
                        'display_column' => 'name',
                    ],
                ],
            ],
        ];
    }
}
