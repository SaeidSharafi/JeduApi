<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Wallet;

use App\Actions\Admin\Wallet\WithdrawFromWalletAction;
use App\Data\Wallet\WithdrawFromWalletData;
use App\Http\Controllers\Controller;
use App\Models\Wallet;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class WithdrawFromWalletController extends Controller
{
    public function __invoke(WithdrawFromWalletData $data): JsonResource
    {
        Gate::authorize('withdrawal', Wallet::class);

        $transaction = app(WithdrawFromWalletAction::class)->execute($data, auth('staff')->user());

        return JsonResource::make($transaction);
    }
}
