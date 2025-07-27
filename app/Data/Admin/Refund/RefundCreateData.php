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
        public readonly ?string $admin_notes,
    ) {
    }

    public static function rules(ValidationContext $context): array
    {
        return [
            'deduction_amount'  => [
                'nullable', 'required_without:deduction_percent', 'integer',
                'min:0',
            ],
            'deduction_percent' => [
                'nullable', 'required_without:deduction_amount', 'integer', 'min:0',
                'max:100',
            ],

            'status'      => ['required', Rule::enum(RefundStatusEnum::class)],
            'admin_notes' => ['nullable', 'string'],

            'transaction_details'               => ['required', 'array'],
            'transaction_details.receiver_name' => ['required', 'string', 'max:255'],
            'transaction_details.card_number'   => ['required', 'string', 'digits:16'],
            'transaction_details.iban_number'   => ['required', 'string', new IbanNumberRule()],
            'transaction_details.tracking_code' => ['nullable', 'string', 'max:255'],
        ];
    }
}
