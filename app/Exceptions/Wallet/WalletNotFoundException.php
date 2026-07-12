<?php

declare(strict_types=1);

namespace App\Exceptions\Wallet;

use App\Contracts\ApiResponseInterface;
use App\Exceptions\Payment\PaymentException;
use Illuminate\Http\Request;

final class WalletNotFoundException extends WalletException
{
    public function __construct(public readonly int $userId)
    {
        parent::__construct(__('validation.custom.wallet_not_found'));
    }

    public function errorCode(): string
    {
        return 'WALLET_NOT_FOUND';
    }

    protected function customUserMessage(): string
    {
        return $this->getMessage();
    }

    protected function customMetadata(): array
    {
        return ['user_id' => $this->userId];
    }
    public function render(Request $request): ApiResponseInterface
    {

        return apiResponse()->validationErrors(
            [$this->getMessage()],
            metadata: $this->metadata(),
        );
    }
}
