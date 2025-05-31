<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\CreateAdminAction;
use App\Actions\Admin\DeleteAdminAction;
use App\Actions\Admin\UpdateAdminAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\AdminListItemData;
use App\Data\Admin\CreateAdminData;
use App\Data\Admin\ShowAdminData;
use App\Data\Admin\UpdateAdminData;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Admin Management
 *
 * APIs for managing admins in the system.
 *
 * @authenticated Admin
 */
final class AdminController extends Controller
{
    /**
     * Display a listing of the Admin.
     *
     * @queryParam filter[name] string Filter by admin name. Example: John Doe
     * @queryParam filter[email] string Filter by admin email. Example: johndoe@example.com
     * @queryParam filter[phone] string Filter by admin phone number. Example: 09301236547
     * @queryParam filter[role] string Filter by admin role name. Example: admin
     * @queryParam sort string Sort by a field. Allowed values: name, email, phone, created_at, updated_at.
     *              Prefix with '-' for descending order
     *
     * @responseFile 200 responses/admins/index.json
     */
    public function index(): ApiResponseInterface
    {
        Gate::authorize('viewAny', Admin::class);
        $admins = QueryBuilder::for(Admin::class)
            ->allowedFilters([
                'name', 'email', 'phone',
                AllowedFilter::exact('role', 'roles.name'),
            ])
            ->allowedSorts(['name', 'email', 'phone', 'created_at', 'updated_at'])
            ->defaultSort('-created_at')
            ->with('roles')
            ->paginate(request()->integer('per_page', 10));

        return response()->success(AdminListItemData::collect($admins));
    }

    /**
     * Store a newly created Admin in database.
     *
     * @response 201
     * @responseFile 403 responses/403.json
     */
    public function store(CreateAdminData $data, CreateAdminAction $action): ApiResponseInterface
    {
        Gate::authorize('create', Admin::class);
        $action->handle($data);

        return response()->created(model: Admin::class);
    }

    /**
     * Display the specified Admin.
     *
     * @responseFile 200 responses/admins/show.json
     * @responseFile 403 responses/403.json
     * @responseFile 404 responses/404.json
     */
    public function show(Admin $admin): ApiResponseInterface
    {
        Gate::authorize('view', $admin);
        $admin->load('roles');

        return response()->success(ShowAdminData::from($admin));
    }

    /**
     * Update the specified Admin in database.
     *
     * @responseFile 200 responses/admins/show.json
     * @responseFile 403 responses/403.json
     * @responseFile 404 responses/404.json
     */
    public function update(UpdateAdminData $data, Admin $admin, UpdateAdminAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $admin);
        $action->handle($data, $admin);

        return response()->updated(ShowAdminData::from($admin), model: Admin::class);
    }

    /**
     * Remove the specified Admin from database.
     *
     * @response 204
     */
    public function destroy(Admin $admin, DeleteAdminAction $action): JsonResponse
    {
        Gate::authorize('delete', $admin);
        $action->handle($admin);

        return response()->noContentJson();

    }
}
