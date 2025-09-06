<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Wallet;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Wallet\WalletData;
use App\Http\Controllers\Controller;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Admin - Wallet Management
 *
 * @authenticated
 */
class AdminWalletController extends Controller
{
    /**
     * Display a paginated listing of wallets.
     *
     * @queryParam filter[user_id] int Filter by user ID. Example: 101
     * @queryParam filter[status] string Filter by wallet status. Example: active
     * @queryParam sort string Sort by a field. Allowed values: id, balance, created_at. Example: -created_at
     * @queryParam per_page integer Number of results per page. Example: 15
     *
     * @responseFile 200 responses/wallet/index.json
     */
    public function index(Request $request): ApiResponseInterface
    {
        Gate::authorize('viewAny', Wallet::class);
        $wallets = QueryBuilder::for(Wallet::class)
            ->allowedFilters(['user_id', 'status'])
            ->allowedSorts(['id', 'balance', 'created_at'])
            ->paginate($request->integer('per_page', 15));
        return response()->success(WalletData::collect($wallets));
    }

    /**
     * Display the specified wallet.
     *
     * @responseFile 200 responses/wallet/show.json
     * @responseFile 404 responses/404.json
     */
    public function show(Wallet $wallet): ApiResponseInterface
    {
        Gate::authorize('view', $wallet);
        return response()->success(WalletData::from($wallet));
    }
}
