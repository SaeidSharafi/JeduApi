<?php

declare(strict_types=1);

namespace App\Services\Discounts\Configs;

use App\Enums\Operators\MathOperatorEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

final class CartValueConditionConfigData extends Data
{
    public function __construct(
        public MathOperatorEnum $operator,
        public int $value,      // The value to compare against
        /**
         * Measurement-only choice: measure the customer's whole commitment
         * (deposit lines counted at their paid deposit) instead of only the
         * lines being paid in full. Never changes what gets discounted.
         */
        public bool $measure_whole_commitment,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @codeCoverageIgnore
     */
    public static function rules(): array
    {
        return [
            'operator'                 => ['required', Rule::enum(MathOperatorEnum::class)],
            'value'                    => ['required', 'integer', 'min:0'],
            'measure_whole_commitment' => ['boolean'],
        ];
    }
}
