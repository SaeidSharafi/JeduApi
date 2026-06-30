<?php

declare(strict_types=1);

namespace App\Exceptions\Payment;

use App\Contracts\ApiResponseInterface;
use App\Http\Responses\ApiFailResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class InsufficientWalletBalanceException extends Exception
{
    public function __construct(
        public readonly int $availableBalance,
        public readonly int $requiredBalance,
        public readonly int $shortfall,
        string $message = ''
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
        return apiResponse()->validationErrors(
            ['wallet_balance' => $this->getMessage()],
            metadata: [
                'error_code'        => 'INSUFFICIENT_WALLET_BALANCE',
                'available_balance' => $this->availableBalance,
                'required_balance'  => $this->requiredBalance,
                'shortfall'         => $this->shortfall,
            ],
        );
    }
}
