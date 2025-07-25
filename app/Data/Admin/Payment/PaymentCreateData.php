<?php

declare(strict_types=1);

namespace App\Data\Admin\Payment;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class PaymentCreateData extends Data
{
    public function __construct(
        public string $method,
        public string $status,
        public ?BankTransferPaymentData $data,
        public ?string $admin_notes,

    ) {
    }

    public static function rules(ValidationContext $context): array
    {
        $now = verta()->format('Y-m-d');

        return [
            'method'                => ['required', 'string'],
            'status'                => ['required', 'string'],
            'admin_notes'           => ['nullable', 'string', 'max:1000'],
            'data'                  => ['nullable', 'array'],
            // We can validate the types if data *is* present, but not require them.
            'data.transaction_id'   => ['nullable', 'string', 'max:255'],
            'data.transaction_date' => ['nullable', 'jdate:Y-m-d','jdate_before_equal:'.$now.',Y-m-d'],
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
            'method'      => [
                'description' => 'Payment method used for the transaction',
                'example'     => 'credit_card',
            ],
            'status'      => [
                'description' => 'Current status of the payment',
                'example'     => 'pending',
            ],
            'data'        => [
                'description' => 'Additional data related to the payment, such as transaction ID or gateway response',
                'example'     => ['transaction_id' => '123456789'],
            ],
            'admin_notes' => [
                'description' => 'Optional notes for administrative purposes',
                'example'     => 'Payment received, awaiting confirmation.',
            ],
        ];
    }
}
