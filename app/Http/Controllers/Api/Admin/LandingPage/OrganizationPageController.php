<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\LandingPage;

use App\Actions\Admin\Organization\UpdateOrganizationPageAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Organization\OrganizationPageData;
use App\Data\Admin\Organization\OrganizationPageUpdateData;
use App\Http\Controllers\Controller;
use App\Models\OrganizationPage;
use App\Models\Setting;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Landing Pages
 *
 * @authenticated
 */
final class OrganizationPageController extends Controller
{
    /**
     * Get the Organization page configuration.
     *
     * @responseFile 200 resources/responses/admin/landing-pages/organization/show.json
     * @responseFile 403 resources/responses/403.json
     */
    public function show(): ApiResponseInterface
    {
        Gate::authorize('viewAny', Setting::class);

        $page = OrganizationPage::query()->singleton()->with('media')->firstOrFail();

        return apiResponse()->success(OrganizationPageData::fromModel($page));
    }

    /**
     * Fully replace the Organization page configuration.
     *
     * @responseFile 200 resources/responses/admin/landing-pages/organization/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 422 resources/responses/422.json
     */
    public function update(
        OrganizationPageUpdateData $data,
        UpdateOrganizationPageAction $action,
    ): ApiResponseInterface {
        Gate::authorize('update', Setting::class);

        $page = $action->handle($data);

        return apiResponse()->updated(OrganizationPageData::fromModel($page), model: OrganizationPage::class);
    }
}
