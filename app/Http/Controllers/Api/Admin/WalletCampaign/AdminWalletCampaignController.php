<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\WalletCampaign;

use App\Actions\Admin\WalletCampaign\CreateWalletCampaignAction;
use App\Actions\Admin\WalletCampaign\DeleteWalletCampaignAction;
use App\Actions\Admin\WalletCampaign\UpdateWalletCampaignAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\WalletCampaign\WalletCampaignCreateData;
use App\Data\Admin\WalletCampaign\WalletCampaignData;
use App\Exceptions\ModelHasRelationshipDataException;
use App\Http\Controllers\Controller;
use App\Models\WalletCampaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\QueryBuilder;

final class AdminWalletCampaignController extends Controller
{
    /**
     * Display a listing of wallet campaigns
     */
    public function index(Request $request): ApiResponseInterface
    {
        Gate::authorize('viewAny', WalletCampaign::class);

        $campaigns = QueryBuilder::for(WalletCampaign::class)
            ->allowedFilters([
                'name',
                'type',
                'is_active',
                'created_by'
            ])
            ->allowedSorts([
                'name',
                'type',
                'amount',
                'total_usage_count',
                'created_at',
                'starts_at',
                'ends_at'
            ])
            ->allowedIncludes(['creator', 'transactions'])
            ->with(['auditor'])
            ->withCount('transactions')
            ->defaultSort('-created_at')
            ->paginate($request->get('per_page', 15));


        return response()->success(collect($campaigns));
    }

    /**
     * Store a newly created wallet campaign
     */
    public function store(
        WalletCampaignCreateData $data,
        CreateWalletCampaignAction $action
    ): ApiResponseInterface {
        Gate::authorize('create', WalletCampaign::class);

        /** @var \App\Models\Staff $staff */
        $staff = auth('staff')->user();
        $campaign = $action->execute($data, $staff);

        return response()->created(
            WalletCampaignData::from($campaign)
        );
    }

    /**
     * Display the specified wallet campaign
     */
    public function show(WalletCampaign $walletCampaign): ApiResponseInterface
    {
        Gate::authorize('view', $walletCampaign);

        $walletCampaign->load(['auditor', 'transactions']);
        $walletCampaign->loadCount('transactions');

        return response()->success(
            WalletCampaignData::from($walletCampaign)
        );
    }

    /**
     * Update the specified wallet campaign
     */
    public function update(
        WalletCampaign $walletCampaign,
        WalletCampaignCreateData $data,
        UpdateWalletCampaignAction $action
    ): ApiResponseInterface {
        Gate::authorize('update', $walletCampaign);

        $campaign = $action->execute($walletCampaign, $data);
        $campaign->load(['auditor']);

        return response()->success(
            WalletCampaignData::from($campaign)
        );
    }

    /**
     * Remove the specified wallet campaign
     */
    public function destroy(WalletCampaign $walletCampaign, DeleteWalletCampaignAction $action): ApiResponseInterface|JsonResponse
    {
        Gate::authorize('delete', $walletCampaign);
        try {
            $action->handle($walletCampaign);
        }catch (ModelHasRelationshipDataException $exception){
            return response()->error(
                message: __('messages.campaign_has_transactions_cannot_delete'),
                errors: ['campaign' => [__('messages.campaign_has_transactions_cannot_delete')]],
                status: 422
            );
        }

        return response()->noContentJson();
    }
}
