<?php

declare(strict_types=1);

namespace App\Data\Admin\Refund;

use Spatie\LaravelData\Data;

final class RefundOrderData extends Data
{
    public function __construct(
        public readonly ?int $deduction_amount = null,
        public readonly ?int $deduction_percent = null,
        public readonly bool $skip_gateway = false,
        public readonly ?string $admin_notes = null,
        public readonly ?string $receiver_name = null,
        public readonly ?string $card_number = null,
        public readonly ?string $iban = null,
    ) {}

    public static function rules(): array
    {
        return [
            'deduction_amount'  => ['nullable', 'integer', 'min:0'],
            'deduction_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'skip_gateway'      => ['boolean'],
            'admin_notes'       => ['nullable', 'string'],
            'receiver_name'     => ['nullable', 'string', 'max:255'],
            'card_number'       => ['nullable', 'string', 'digits:16'],
            'iban'              => ['nullable', 'string'],
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
                'description' => 'Flat deduction amount in Rials applied to the total refundable sum.',
                'example'     => 100000,
            ],
            'deduction_percent' => [
                'description' => 'Percentage deduction applied against each item\'s original price.',
                'example'     => 10,
            ],
            'skip_gateway' => [
                'description' => 'Skip the payment gateway call and mark refund as completed with a note.',
                'example'     => false,
            ],
            'admin_notes' => [
                'description' => 'Optional admin notes.',
                'example'     => 'Full order refund due to customer request.',
            ],
            'receiver_name' => [
                'description' => 'Receiver name for manual bank transfer refunds.',
                'example'     => 'Ali Rezaei',
            ],
            'card_number' => [
                'description' => '16-digit card number for manual bank transfer refunds.',
                'example'     => '1234567890123456',
            ],
            'iban' => [
                'description' => 'IBAN number for manual bank transfer refunds.',
                'example'     => 'IR123456789012345678901234',
            ],
        ];
    }
}
