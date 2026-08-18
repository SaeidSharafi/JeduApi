<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\WalletCampaign;

use App\Actions\Admin\WalletCampaign\CreateWalletCampaignAction;
use App\Actions\Admin\WalletCampaign\DeleteWalletCampaignAction;
use App\Actions\Admin\WalletCampaign\UpdateWalletCampaignAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\WalletCampaign\WalletCampaignCreateData;
use App\Data\Admin\WalletCampaign\WalletCampaignData;
use App\Http\Controllers\Controller;
use App\Models\WalletCampaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Admin - Wallet Campaign Management
 *
 * @authenticated
 */
final class AdminWalletCampaignController extends Controller
{
    /**
     * Display a listing of wallet campaigns.
     *
     * @queryParam filter[name] string Filter by campaign name. Example: Back to School Bonus
     * @queryParam filter[type] string Filter by campaign type. Allowed values: registration_bonus, birthday_gift, referral_bonus, loyalty_reward, seasonal_bonus, milestone_reward, manual_allocation. Example: loyalty_reward
     * @queryParam filter[is_active] boolean Filter by active status. Example: true
     * @queryParam filter[created_by] int Filter by creator staff ID. Example: 10
     * @queryParam sort string Sort by a field. Allowed values: name, type, amount, total_usage_count, created_at, starts_at, ends_at. Prefix with '-' for descending order. Example: -created_at
     * @queryParam per_page integer Number of results per page. Example: 15
     *
     * @responseFile 200 resources/responses/admin/wallet-campaign/index.json
     */
    public function index(Request $request): ApiResponseInterface
    {
        Gate::authorize('viewAny', WalletCampaign::class);

        $campaigns = QueryBuilder::for(WalletCampaign::class)
            ->allowedFilters([
                'name',
                'type',
                AllowedFilter::exact('is_active'),
                AllowedFilter::exact('created_by'),
            ])
            ->allowedSorts([
                'name',
                'type',
                'amount',
                'total_usage_count',
                'created_at',
                'starts_at',
                'ends_at',
            ])
            ->allowedIncludes(['creator', 'transactions'])
            ->defaultSort('-created_at')
            ->with(['auditor'])
            ->withCount('transactions')
            ->paginate(request()->integer('per_page', config('app.page_size')));

        return apiResponse()->success(WalletCampaignData::collect($campaigns));
    }

    /**
     * Store a newly created wallet campaign.
     *
     * @responseFile 201 resources/responses/admin/wallet-campaign/show.json
     * @responseFile 422 resources/responses/422.json
     */
    public function store(
        WalletCampaignCreateData $data,
        CreateWalletCampaignAction $action
    ): ApiResponseInterface {
        Gate::authorize('create', WalletCampaign::class);

        /** @var \App\Models\Staff $staff */
        $staff    = auth('staff')->user();
        $campaign = $action->execute($data, $staff);

        return apiResponse()->created(
            WalletCampaignData::from($campaign)
        );
    }

    /**
     * Display the specified wallet campaign.
     *
     * @responseFile 200 resources/responses/admin/wallet-campaign/show.json
     * @responseFile 404 resources/responses/404.json
     */
    public function show(WalletCampaign $walletCampaign): ApiResponseInterface
    {
        Gate::authorize('view', $walletCampaign);

        $walletCampaign->load(['auditor', 'transactions']);
        $walletCampaign->loadCount('transactions');

        return apiResponse()->success(
            WalletCampaignData::from($walletCampaign)
        );
    }

    /**
     * Update the specified wallet campaign.
     *
     * @responseFile 200 resources/responses/admin/wallet-campaign/show.json
     * @responseFile 422 resources/responses/422.json
     * @responseFile 404 resources/responses/404.json
     */
    public function update(
        WalletCampaign $walletCampaign,
        WalletCampaignCreateData $data,
        UpdateWalletCampaignAction $action
    ): ApiResponseInterface {
        Gate::authorize('update', $walletCampaign);

        $campaign = $action->execute($walletCampaign, $data);
        $campaign->load(['auditor']);

        return apiResponse()->success(
            WalletCampaignData::from($campaign)
        );
    }

    /**
     * Remove the specified wallet campaign.
     *
     * @responseFile 422 resources/responses/admin/wallet-campaign/422-destroy.json
     * @responseFile 404 resources/responses/404.json
     */
    public function destroy(WalletCampaign $walletCampaign, DeleteWalletCampaignAction $action): ApiResponseInterface|JsonResponse
    {
        Gate::authorize('delete', $walletCampaign);
        $action->handle($walletCampaign);

        return apiResponse()->noContentJson();
    }
}
