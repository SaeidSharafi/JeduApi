<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Wallet;

use App\Actions\Admin\Wallet\CreateWalletAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Wallet\CreateWalletData;
use App\Data\Admin\Wallet\WalletData;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Admin - Wallet Management
 *
 * @authenticated
 */
final class AdminWalletController extends Controller
{
    /**
     * Display the specified wallet.
     *
     * @responseFile 200 resources/responses/admin/wallet/show.json
     * @responseFile 404 resources/responses/404.json
     */
    public function show(User $user): ApiResponseInterface
    {
        $wallet = $user->wallet;
        Gate::authorize('view', $wallet);
        $wallet->load('user');

        return apiResponse()->success(WalletData::from($wallet));
    }

    /**
     * Create a new wallet for a user.
     *
     * @responseFile 201 resources/responses/admin/wallet/create.json
     * @responseFile 422 resources/responses/422.json
     */
    public function store(CreateWalletData $data,User $user, CreateWalletAction $action): ApiResponseInterface
    {
        Gate::authorize('create', Wallet::class);
        $wallet = $action->handle($data, $user);

        return apiResponse()->created(WalletData::from($wallet));
    }
}
