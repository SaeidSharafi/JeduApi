<?php

declare(strict_types=1);

namespace App\Exceptions\Wallet;

use App\Contracts\ApiResponseInterface;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Exceptions\Payment\PaymentException;
use App\Models\Order;
use Illuminate\Http\Request;

final class WalletInsufficientBalanceException extends PaymentException
{
    public function __construct(
        public readonly int $availableBalance,
        public readonly int $requiredBalance,
        public readonly int $shortfall,
        public readonly ?string $orderIncrementId = null,
        public readonly ?TransactionSourceEnum $sourceType = null,
        public readonly ?int $sourceId = null,
        string $message = '',
    ) {
        $message = $message ?: __('validation.custom.insufficient_balance');
        parent::__construct($message);
    }

    public function getAvailableBalance(): int
    {
        return $this->availableBalance;
    }

    public function getRequiredBalance(): int
    {
        return $this->requiredBalance;
    }

    public function getShortfall(): int
    {
        return $this->shortfall;
    }

    public function render(Request $request): ApiResponseInterface
    {
        return apiResponse()->validationErrors(
            ['wallet_balance' => $this->getMessage()],
            metadata: $this->metadata(),
        );
    }

    public function errorCode(): string
    {
        return 'INSUFFICIENT_WALLET_BALANCE';
    }

    protected function customMetadata(): array
    {

        $metadata = [
            'error_code'        => 'INSUFFICIENT_WALLET_BALANCE',
            'available_balance' => $this->availableBalance,
            'required_balance'  => $this->requiredBalance,
            'shortfall'         => $this->shortfall,
        ];

        // Only resolve order_id when the shortfall actually came from an order payment.
        if ($this->sourceType === TransactionSourceEnum::ORDER && $this->sourceId !== null) {
            $metadata['order_id'] = Order::find($this->sourceId)?->increment_id;
        }

        return $metadata;
    }
}
