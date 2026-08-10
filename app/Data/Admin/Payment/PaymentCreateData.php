<?php

declare(strict_types=1);

namespace App\Data\Admin\Payment;

use App\Helpers\JalaliDateHelper;
use App\Rules\ValidNormalizedJalaliDateRule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class PaymentCreateData extends Data
{
    public function __construct(
        public ?BankTransferPaymentData $data,
        public ?string $admin_notes,

    ) {}

    public static function prepareForPipeline(array $properties): array
    {
        return JalaliDateHelper::toGregorian($properties, [
            'data.transaction_date',
        ]);
    }

    public static function rules(?ValidationContext $context = null): array
    {
        $now = now()->format('Y-m-d');

        return [
            'admin_notes' => ['nullable', 'string', 'max:1000'],
            'data'        => ['nullable', 'array'],
            // Bank transfer validation
            'data.transaction_id'   => ['nullable', 'string', 'max:255'],
            'data.transaction_date' => ['bail', 'nullable', new ValidNormalizedJalaliDateRule, 'date_format:Y-m-d', 'before_or_equal:'.$now],
            'data.sender_name'      => ['nullable', 'string', 'max:255'],
            'data.notes'            => ['nullable', 'string', 'max:1000'],
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
            'data' => [
                'description' => 'Additional data related to the payment, such as transaction ID or gateway response',
                'example'     => ['transaction_id' => '123456789'],
            ],
            'admin_notes' => [
                'description' => 'Optional notes for administrative purposes',
                'example'     => 'Payment received, awaiting confirmation.',
            ],
            'data.transaction_id' => [
                'description' => 'The unique identifier for the bank transaction.',
                'example'     => 'TX123456789',
            ],
            'data.transaction_date' => [
                'description' => 'The date when the bank transaction occurred (Jalali date format).',
                'example'     => '1402-01-15',
            ],
            'data.sender_name' => [
                'description' => 'The name of the person who sent the bank transfer.',
                'example'     => 'Ali Rezaei',
            ],
            'data.notes' => [
                'description' => 'Any additional notes related to the bank transfer.',
                'example'     => 'Payment for order #1234',
            ],
        ];
    }
}
