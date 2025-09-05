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

class AdminWalletController extends Controller
{
    public function index(Request $request): ApiResponseInterface
    {
        Gate::authorize('viewAny', Wallet::class);
        $wallets = QueryBuilder::for(Wallet::class)
            ->allowedFilters(['user_id', 'status'])
            ->allowedSorts(['id', 'balance', 'created_at'])
            ->paginate($request->integer('per_page', 15));
        return response()->success(WalletData::collect($wallets));
    }

    public function show(Wallet $wallet): ApiResponseInterface
    {
        Gate::authorize('view', $wallet);
        return response()->success(WalletData::from($wallet));
    }
}
