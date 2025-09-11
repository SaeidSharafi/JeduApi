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
        public bool $include_prepayments, // If true, check against the cart's full value
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @codeCoverageIgnore
     */
    public static function rules(): array
    {
        return [
            'operator'            => ['required', Rule::enum(MathOperatorEnum::class)],
            'value'               => ['required', 'integer', 'min:0'],
            'include_prepayments' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     *
     * @codeCoverageIgnore
     */
    public static function descriptions(): array
    {
        return [
            'operator'            => 'The mathematical operator to use for comparison.',
            'value'               => 'The value to compare against the cart total.',
            'include_prepayments' => 'If true, the condition checks against the full cart value including prepayments.',
        ];
    }
}
