<?php

declare(strict_types=1);

namespace App\Services\Discounts\Configs;

use App\Enums\Operators\MatchPolicyEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

final class SpecificProductsInCartData extends Data
{
    /**
     * @param  array<int, int>  $product_ids
     */
    public function __construct(
        public array $product_ids,
        public MatchPolicyEnum $match_policy = MatchPolicyEnum::ANY,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @codeCoverageIgnore
     */
    public static function rules(): array
    {
        return [
            'product_ids'   => ['required', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'match_policy'  => ['required', Rule::enum(MatchPolicyEnum::class)],
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
            'product_ids' => [
                'model_reference' => [
                    'table'          => 'products',
                    'column'         => 'id',
                    'display_column' => 'name',
                ],
            ],
        ];
    }
}
