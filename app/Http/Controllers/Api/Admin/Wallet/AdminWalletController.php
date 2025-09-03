<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Wallet;

use App\Actions\Admin\Wallet\GetWalletBalanceAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Wallet\UpdateWalletData;
use App\Data\Wallet\WalletData;
use App\Http\Controllers\Controller;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
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

    public function update(UpdateWalletData $data, Wallet $wallet): ApiResponseInterface
    {
        Gate::authorize('update', $wallet);

        $wallet->update([
            'balance' => $data->balance,
            'gift_balance' => $data->gift_balance,
            'status' => $data->status,
        ]);

        return response()->created(WalletData::from($wallet));
    }
    public function balance(int $userId): JsonResource
    {
        Gate::authorize('viewAny', Wallet::class);

        $balance = (new GetWalletBalanceAction())->execute($userId);

        return JsonResource::make($balance);
    }
}
