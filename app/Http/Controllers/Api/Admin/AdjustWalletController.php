<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Wallet\AdjustWalletAction;
use App\Data\Wallet\AdjustWalletData;
use App\Http\Controllers\Controller;
use App\Models\Wallet;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class AdjustWalletController extends Controller
{
    public function __invoke(AdjustWalletData $data): JsonResource
    {
        Gate::authorize('adjustment', Wallet::class);

        $transaction = app(AdjustWalletAction::class)->execute($data);

        return JsonResource::make($transaction);
    }
}
