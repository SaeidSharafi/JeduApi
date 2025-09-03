<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\Wallet\CreateWalletAction;
use App\Data\Wallet\CreateWalletData;
use App\Http\Controllers\Controller;
use App\Models\Wallet;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class CreateWalletController extends Controller
{
    public function __invoke(CreateWalletData $data): JsonResource
    {
        Gate::authorize('create', Wallet::class);
        $wallet = (new CreateWalletAction())->execute($data);
        return JsonResource::make($wallet);
    }
}
