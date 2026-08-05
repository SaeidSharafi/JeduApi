<?php

declare(strict_types=1);

namespace App\Data\Shop\Student\Payment;

use App\Enums\Payment\PaymentPurposeEnum;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * Request DTO for the student payment list endpoint.
 *
 * Supports filtering by payment purpose and pagination.
 */
final class PaymentListData extends Data
{
    public function __construct(
        public readonly string|Optional $purpose = new Optional(),
        public readonly int $per_page = 15,
    ) {}

    /**
     * Permissive rules: invalid purpose values are intentionally ignored by
     * the controller (tryFrom returns null), so we don't validate the enum
     * here. We only validate the shape so Scribe documents the field.
     *
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public static function rules(): array
    {
        return [
            'purpose'  => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @codeCoverageIgnore
     */
    public static function bodyParameters(): array
    {
        $purposes = array_column(PaymentPurposeEnum::cases(), 'value');

        return [
            'purpose' => [
                'description' => 'Filter payments by purpose. One of: '.implode(', ', $purposes).'.',
                'example'     => PaymentPurposeEnum::ORDER->value,
            ],
            'per_page' => [
                'description' => 'Number of items per page.',
                'example'     => 15,
            ],
        ];
    }
}
