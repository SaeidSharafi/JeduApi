<?php

declare(strict_types=1);

namespace App\Data\Admin\Payment;

use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class DigipayInquireRefundRequestData extends Data
{
    public function __construct(
        #[Required, StringType]
        public string $refund_provider_id,
        #[Required, IntegerType]
        public int $type,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'refund_provider_id' => ['required', 'string'],
            'type'               => ['required', 'integer'],
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
            'refund_provider_id' => [
                'description' => 'Provider ID used when creating refund (REFUND-{payment_id}-{timestamp}).',
                'example'     => 'REFUND-123-1719000000',
            ],
            'type' => [
                'description' => 'Payment type: 0=IPG, 11=Wallet, 5=Credit, 13=BNPL.',
                'example'     => 0,
            ],
        ];
    }
}
