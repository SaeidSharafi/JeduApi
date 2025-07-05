<?php

namespace App\Data\Order;

use App\Enums\OrderItemStatusEnum;
use App\Enums\OrderStatusEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class OrderCreateData extends Data
{
    public function __construct(
        public string $status,
        public int $customer_id,
        #[DataCollectionOf(OrderItemCreateData::class)]
        public array $items,
        public ?string $applied_coupon_code,
        public ?string $admin_notes,
    ) {
    }

    public static function rules(ValidationContext $context): array
    {
        return [
            'status'                             => ['required', 'string', Rule::enum(OrderStatusEnum::class)],
            'customer_id'                        => ['required', 'integer', 'exists:users,id'],
            'applied_coupon_code'                => ['nullable', 'string', 'max:255'],
            'admin_notes'                        => ['nullable', 'string', 'max:1000'],
            'items'                              => ['required', 'array', 'min:1'],
        ];
    }
}
