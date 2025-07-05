<?php

namespace App\Data\Payment;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class PaymentCreateData extends Data
{
    public function __construct(
        public string $method,
        public string $status,
        public ?string $admin_notes,

    )
    {
    }

    public static function rules(ValidationContext $context): array
    {
        return [
            'method'      => ['required', 'string'],
            'status'      => ['required', 'string'],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
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
            'method' => [
                'description' => 'Payment method used for the transaction',
                'example'     => 'credit_card',
            ],
            'status' => [
                'description' => 'Current status of the payment',
                'example'     => 'pending',
            ],
            'admin_notes' => [
                'description' => 'Optional notes for administrative purposes',
                'example'     => 'Payment received, awaiting confirmation.',
            ],
        ];
    }
}
