<?php

declare(strict_types=1);

namespace App\Exceptions\Payment;

use App\Contracts\ApiResponseInterface;
use Illuminate\Http\Request;

final class OrderFullyPaidException extends PaymentException
{
    public function __construct(
        public readonly ?string $orderIncrementId = null,
    ) {
        parent::__construct(__('messages.order.already_fully_paid', ['order_id' => $this->orderIncrementId]));
    }

    public function errorCode(): string
    {
        return 'ORDER_FULLY_PAID';
    }

    public function render(Request $request): ApiResponseInterface
    {
        return apiResponse()->validationErrors(
            [$this->getMessage()],
            metadata: $this->metadata(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function customMetadata(): array
    {
        return array_filter([
            'increment_id' => $this->orderIncrementId,
        ]);
    }
}
