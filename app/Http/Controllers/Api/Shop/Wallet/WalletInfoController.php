<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Wallet;

use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Customer\WalletData;
use App\Http\Controllers\Controller;

/**
 * @group Shop - Wallet
 *
 * @authenticated
 */
final class WalletInfoController extends Controller
{
    /**
     * Get wallet information.
     *
     * @responseFile 200 resources/responses/shop/wallet/info.json
     *
     * @response 401 {
     *   "message": "Unauthorized"
     * }
     */
    public function __invoke(): ApiResponseInterface
    {
        $user = auth()->user();
        abort_unless($user !== null, 401, 'Unauthorized');

        $walletData = $user->wallet;

        return apiResponse()->success(WalletData::from($walletData));
    }
}
