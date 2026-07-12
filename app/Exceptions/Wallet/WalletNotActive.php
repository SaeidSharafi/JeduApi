<?php

namespace App\Exceptions\Wallet;

use App\Contracts\ApiResponseInterface;
use App\Exceptions\Wallet\WalletException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WalletNotActive extends WalletException
{

    public function __construct()
    {
        parent::__construct(__('validation.custom.wallet_not_active'));
    }


    public function errorCode(): string
    {
        return 'WALLET_IS_NOT_ACTIVE';
    }

    public function render(Request $request): ApiResponseInterface
    {

        return apiResponse()->validationErrors(
            [$this->getMessage()],
            metadata: $this->metadata(),
        );
    }
}
