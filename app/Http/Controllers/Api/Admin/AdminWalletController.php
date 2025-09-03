<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\QueryBuilder;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

class AdminWalletController extends Controller
{
    public function index(Request $request): JsonResource
    {
        Gate::authorize('viewAny', Wallet::class);
        $wallets = QueryBuilder::for(Wallet::class)
            ->allowedFilters(['user_id', 'status'])
            ->allowedSorts(['id', 'balance', 'created_at'])
            ->paginate(20);
        return JsonResource::collection($wallets);
    }

    public function show(Wallet $wallet): JsonResource
    {
        Gate::authorize('view', Wallet::class);
        return JsonResource::make($wallet);
    }
}
