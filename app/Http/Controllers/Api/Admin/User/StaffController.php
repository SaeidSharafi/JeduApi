<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\User;

use App\Actions\Admin\Staff\CreateStaffAction;
use App\Actions\Admin\Staff\DeleteStaffAction;
use App\Actions\Admin\Staff\UpdateStaffAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Staff\CreateStaffData;
use App\Data\Admin\Staff\ShowStaffData;
use App\Data\Admin\Staff\StaffListItemData;
use App\Data\Admin\Staff\UpdateStaffData;
use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Admin - Staff Management
 *
 * APIs for managing staff in the system.
 *
 * @authenticated Staff
 */
final class StaffController extends Controller
{
    /**
     * Display a listing of the Staff.
     *
     * @queryParam filter[name] string Filter by staff name. Example: John Doe
     * @queryParam filter[email] string Filter by staff email. Example: johndoe@example.com
     * @queryParam filter[phone] string Filter by admin phone number. Example: 09301236547
     * @queryParam filter[role] string Filter by admin role name. Example: admin
     * @queryParam sort string Sort by a field. Allowed values: name, email, phone, created_at, updated_at.
     *              Prefix with '-' for descending order
     *
     * @responseFile 200 resources/responses/admin/staff/index.json
     */
    public function index(): ApiResponseInterface
    {
        Gate::authorize('viewAny', Staff::class);
        $staff = QueryBuilder::for(Staff::class)
            ->allowedFilters([
                'name', 'email', 'phone',
                AllowedFilter::exact('role', 'roles.name'),
            ])
            ->allowedSorts(['name', 'email', 'phone', 'created_at', 'updated_at'])
            ->defaultSort('-created_at')
            ->with('roles')
            ->paginate(request()->integer('per_page', 15))
            ->withQueryString();

        return response()->success(StaffListItemData::collect($staff));
    }

    /**
     * Store a newly created Staff in database.
     *
     * @response 201
     *
     * @responseFile 403 resources/responses/403.json
     */
    public function store(CreateStaffData $data, CreateStaffAction $action): ApiResponseInterface
    {
        Gate::authorize('create', Staff::class);
        $action->handle($data);

        return response()->created(model: Staff::class);
    }

    /**
     * Display the specified Staff.
     *
     * @responseFile 200 resources/responses/admin/staff/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     */
    public function show(Staff $staff): ApiResponseInterface
    {
        Gate::authorize('view', $staff);
        $staff->load('roles');

        return response()->success(ShowStaffData::from($staff));
    }

    /**
     * Update the specified Staff in database.
     *
     * @responseFile 200 resources/responses/admin/staff/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     */
    public function update(UpdateStaffData $data, Staff $staff, UpdateStaffAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $staff);
        $action->handle($data, $staff);

        return response()->updated(ShowStaffData::from($staff), model: Staff::class);
    }

    /**
     * Remove the specified Staff from database.
     *
     * @response 204
     */
    public function destroy(Staff $staff, DeleteStaffAction $action): JsonResponse
    {
        Gate::authorize('delete', $staff);
        $action->handle($staff);

        return response()->noContentJson();

    }
}
