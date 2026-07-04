<?php

declare(strict_types=1);

namespace App\Exceptions\Payment;

use App\Contracts\ApiResponseInterface;
use Exception;
use Illuminate\Http\Request;

final class InsufficientWalletBalanceException extends Exception
{
    public function __construct(
        public readonly int $availableBalance,
        public readonly int $requiredBalance,
        public readonly int $shortfall,
        public readonly ?string $orderIncrementId = null,
        string $message = '',
    ) {
        $message = $message ?: __('validation.custom.checkout.insufficient_wallet_balance');
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
        $metadata = [
            'error_code'        => 'INSUFFICIENT_WALLET_BALANCE',
            'available_balance' => $this->availableBalance,
            'required_balance'  => $this->requiredBalance,
            'shortfall'         => $this->shortfall,
        ];

        if ($this->orderIncrementId !== null) {
            $metadata['order_id'] = $this->orderIncrementId;
        }

        return apiResponse()->validationErrors(
            ['wallet_balance' => $this->getMessage()],
            metadata: $metadata,
        );
    }
}
