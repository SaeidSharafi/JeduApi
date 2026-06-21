<?php

declare(strict_types=1);

namespace App\Data\Admin\Payment;

use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class DigipayRefundRequestData extends Data
{
    public function __construct(
        #[IntegerType, Min(1000)]
        public readonly ?int $amount = null,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'amount' => ['nullable', 'integer', 'min:1000'],
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
            'amount' => [
                'description' => 'The refund amount in Rials. If null, a full refund will be processed.',
                'example'     => 500000,
            ],
        ];
    }
}
