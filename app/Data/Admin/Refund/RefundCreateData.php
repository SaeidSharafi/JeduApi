<?php

declare(strict_types=1);

namespace App\Data\Admin\Refund;

use App\Enums\Order\RefundStatusEnum;
use App\Rules\IbanNumberRule;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class RefundCreateData extends Data
{
    public function __construct(
        public readonly ?int $deduction_amount,
        #[Max(100)]
        public readonly ?int $deduction_percent,
        public readonly RefundTransactionData $transaction_details,
        public readonly string $status,
        public readonly bool $skip_gateway = false,
        public readonly ?string $admin_notes = null,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'deduction_amount' => [
                'nullable', 'required_without:deduction_percent', 'integer',
                'min:0',
            ],
            'deduction_percent' => [
                'nullable', 'required_without:deduction_amount', 'integer', 'min:0',
                'max:100',
            ],

            'status'       => ['required', Rule::enum(RefundStatusEnum::class)],
            'skip_gateway' => ['boolean'],
            'admin_notes'  => ['nullable', 'string'],

            'transaction_details'               => ['required', 'array'],
            'transaction_details.receiver_name' => ['required', 'string', 'max:255'],
            'transaction_details.card_number'   => ['required', 'string', 'digits:16'],
            'transaction_details.iban_number'   => ['required', 'string', new IbanNumberRule()],
            'transaction_details.tracking_code' => ['nullable', 'string', 'max:255'],
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
            'deduction_amount' => [
                'description' => 'Amount to deduct from the refund.',
                'example'     => 10000,
            ],
            'deduction_percent' => [
                'description' => 'Percent to deduct from the refund.',
                'example'     => 10,
            ],
            'transaction_details' => [
                'description' => 'Transaction details for the refund.',
                'example'     => [
                    'receiver_name' => 'Ali Rezaei',
                    'card_number'   => '1234567890123456',
                    'iban_number'   => 'IR123456789012345678901234',
                    'tracking_code' => 'TRK987654',
                ],
            ],
            'transaction_details.receiver_name' => [
                'description' => 'Name of the receiver.',
                'example'     => 'Ali Rezaei',
            ],
            'transaction_details.card_number' => [
                'description' => 'Card number of the receiver.',
                'example'     => '1234567890123456',
            ],
            'transaction_details.iban_number' => [
                'description' => 'IBAN number of the receiver.',
                'example'     => 'IR123456789012345678901234',
            ],
            'transaction_details.tracking_code' => [
                'description' => 'Optional tracking code for the transaction.',
                'example'     => 'TRK987654',
            ],
            'status' => [
                'description' => 'Refund status value.',
                'example'     => 'pending',
            ],
            'skip_gateway' => [
                'description' => 'Whether to skip the payment gateway refund processing.',
                'example'     => false,
            ],
            'admin_notes' => [
                'description' => 'Optional admin notes for the refund.',
                'example'     => 'Refund requested by customer.',
            ],
        ];
    }
}
