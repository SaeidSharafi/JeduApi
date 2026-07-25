<?php

declare(strict_types=1);

namespace App\Services\Discounts\Configs;

use App\Enums\Operators\MatchPolicyEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

final class ProductCategoryConditionConfigData extends Data
{
    public function __construct(
        /** @var int[] */
        public array $category_ids,
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
            'category_ids'   => ['required', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'match_policy'   => ['required', Rule::enum(MatchPolicyEnum::class)],
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
            'category_ids' => [
                'item' => [
                    'item_type'       => 'model',
                    'model_reference' => [
                        'table'          => 'categories',
                        'column'         => 'id',
                        'display_column' => 'name',
                    ],
                ],
            ],
        ];
    }
}
