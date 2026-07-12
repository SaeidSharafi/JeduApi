<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Content;

use App\Actions\Admin\Partner\CreatePartnerAction;
use App\Actions\Admin\Partner\DeleteCPartnerAction;
use App\Actions\Admin\Partner\UpdatePartnerAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Partner\PartnerCreateData;
use App\Data\Admin\Partner\PartnerData;
use App\Data\Admin\Partner\PartnerListItemData;
use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Admin - Settings - Partner
 */
final class PartnerController extends Controller
{
    /**
     * Display list of partners.
     *
     * @queryParam filter[title] string Filter by title. Example: Partner
     * @queryParam filter[show_in] string Filter by show_in. can be 'home' or 'course'. Example: home
     * @queryParam filter[is_active] boolean Filter by active status. Example: 1
     * @queryParam sort string Sort by a field. Allowed values: order, title, created_at. Prefix with '-' for descending order (e.g., -title for descending by title). Example: order
     *
     * @responseFile 200 resources/responses/admin/settings/partner/index.json
     */
    public function index(): ApiResponseInterface
    {
        Gate::authorize('viewAny', Partner::class);
        $partners = QueryBuilder::for(Partner::class)
            ->defaultSort('order')
            ->allowedFilters([
                'title',
                AllowedFilter::exact('show_in'),
                AllowedFilter::exact('is_active')
            ])
            ->allowedSorts('order', 'title', 'created_at')
            ->paginate(request()->integer('per_page', 15))
            ->withQueryString();

        return apiResponse()->success(PartnerListItemData::collect($partners));
    }

    /**
     * Display the specified partner.
     *
     * @responseFile 200 resources/responses/admin/settings/partner/show.json
     */
    public function show(Partner $partner): ApiResponseInterface
    {
        Gate::authorize('view', $partner);
        $partner->load('media');

        return apiResponse()->success(PartnerData::from([
            ...$partner->toArray(),
            'image' => $partner->getImage(),
        ]));
    }

    /**
     * Store a newly created partner.
     *
     * @responseFile 201 resources/responses/admin/settings/partner/show.json
     */
    public function store(PartnerCreateData $data, CreatePartnerAction $action): ApiResponseInterface
    {
        Gate::authorize('create', Partner::class);
        $partner = $action->handle($data);
        $partner->load('media');

        return apiResponse()->created(PartnerData::from([
            ...$partner->toArray(),
            'image' => $partner->getImage(),
        ]));
    }

    /**
     * Update the specified partner.
     *
     * @responseFile 200 resources/responses/admin/settings/partner/show.json
     */
    public function update(PartnerCreateData $data, Partner $partner, UpdatePartnerAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $partner);
        $partner = $action->handle($partner, $data);
        $partner->load('media');

        return apiResponse()->updated(PartnerData::from([
            ...$partner->toArray(),
            'image' => $partner->getImage(),
        ]), model: Partner::class);
    }

    /**
     * Remove the specified partner.
     *
     * @response 204
     */
    public function destroy(Partner $partner, DeleteCPartnerAction $action): JsonResponse
    {
        Gate::authorize('delete', $partner);
        $action->handle($partner);

        return apiResponse()->noContentJson();
    }
}
