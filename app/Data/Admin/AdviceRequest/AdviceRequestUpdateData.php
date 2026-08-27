<?php

declare(strict_types=1);

namespace App\Data\Admin\AdviceRequest;

use App\Enums\InboundRequestStatusEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class AdviceRequestUpdateData extends Data
{
    public function __construct(
        public string $status,
        public ?string $note = null,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(InboundRequestStatusEnum::class)],
            'note'   => ['nullable', 'string', 'max:1000'],
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
            'status' => [
                'description' => 'Advice request status. Must be a valid InboundRequestStatusEnum value.',
                'example'     => InboundRequestStatusEnum::PENDING->value,
            ],
            'note' => [
                'description' => 'Optional note for the advice request.',
                'example'     => 'Additional context for the request.',
            ],
        ];
    }
}
