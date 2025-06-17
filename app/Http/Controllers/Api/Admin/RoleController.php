<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Role\CreateRoleAction;
use App\Actions\Role\DeleteRoleAction;
use App\Actions\Role\UpdateRoleAction;
use App\Data\Role\CreateRoleData;
use App\Data\Role\RoleListItemData;
use App\Data\Role\ShowRoleData;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Admin - Admin - Roles
 *
 * APIs for managing roles in the admin panel.
 *
 * @authenticated
 */
final class RoleController extends Controller
{
    /**
     * Display a listing of the roles.
     *
     * @queryParam filter[name] string Filter roles by name. Example: admin
     * @queryParam sort string Sort roles by a field. Allowed values: name.
     *   Prefix with '-' for descending order (e.g., -name for descending by name). Example: name
     * @queryParam per_page integer Number of results per page. Default is 15. Example: 20
     *
     * @responseFile 200 responses/role/index.json
     */
    public function index()
    {
        Gate::authorize('viewAny', Role::class);
        $roles = QueryBuilder::for(Role::class)
            ->allowedFilters(['name'])
            ->allowedSorts(['name'])
            ->paginate(request()->integer('per_page', 15))
            ->withQueryString();

        return response()->success(RoleListItemData::collect($roles));
    }

    /**
     * Store a newly created role in storage.
     *
     * @response 201
     * @response 403
     */
    public function store(CreateRoleData $data, CreateRoleAction $action)
    {
        Gate::authorize('create', Role::class);
        $action->handle($data);

        return response()->created();
    }

    /**
     * Display the specified role.
     *
     * @responseFile 200 responses/role/show.json
     *
     * @response 403
     * @response 404
     */
    public function show(Role $role)
    {
        Gate::authorize('view', $role);
        $role->load('permissions');

        return response()->success(ShowRoleData::fromModel($role));
    }

    /**
     * Update the specified role in storage.
     *
     * @responseFile 200 responses/role/show.json
     *
     * @response 403
     * @response 404
     */
    public function update(CreateRoleData $data, Role $role, UpdateRoleAction $action)
    {
        Gate::authorize('update', $role);
        $role->load('permissions');
        $action->handle($data, $role);

        return response()->updated(ShowRoleData::fromModel($role));
    }

    /**
     * Remove the specified role from storage.
     *
     * @response 204
     */
    public function destroy(Role $role, DeleteRoleAction $action)
    {
        Gate::authorize('delete', $role);
        $action->handle($role);

        return response()->noContentJson();
    }
}
