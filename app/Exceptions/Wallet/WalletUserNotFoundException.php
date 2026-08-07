<?php

declare(strict_types=1);

namespace App\Exceptions\Wallet;

use App\Contracts\ApiResponseInterface;
use App\Exceptions\Payment\PaymentException;
use Illuminate\Http\Request;

final class WalletUserNotFoundException extends WalletException
{
    public function __construct(public readonly int $userId)
    {
        parent::__construct(__('validation.custom.user_not_found'));
    }

    public function errorCode(): string
    {
        return 'USER_NOT_FOUND';
    }

    protected function customUserMessage(): string
    {
        return $this->getMessage();
    }

    /**
     * @return array<string, mixed>
     */
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
