<?php

namespace App\Data\Order;

use App\Enums\OrderItemStatusEnum;
use App\Enums\OrderStatusEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class OrderUpdateData extends Data
{
    public function __construct(
        public string $status,
    ) {
    }

    public static function rules(ValidationContext $context): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(OrderStatusEnum::class)],
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
                'description' => 'Order status',
                'example'     => OrderStatusEnum::PENDING->value,
            ],
        ];
    }
}
