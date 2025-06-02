<?php

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

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        Gate::authorize('viewAny', Role::class);
        $roles = QueryBuilder::for(Role::class)
            ->allowedFilters(['name'])
            ->allowedSorts(['name'])
            ->paginate(request()->integer('per_page', 15));

        return response()->success(RoleListItemData::collect($roles));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateRoleData $data, CreateRoleAction $action)
    {
        Gate::authorize('create', Role::class);
        $action->handle($data);
        return response()->created();
    }

    /**
     * Display the specified resource.
     */
    public function show(Role $role)
    {
        Gate::authorize('view', $role);
        $role->load('permissions');

        return response()->success(ShowRoleData::fromModel($role));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CreateRoleData $data, Role $role, UpdateRoleAction $action)
    {
        Gate::authorize('update', $role);
        $role->load('permissions');
        $action->handle($data, $role);

        return response()->updated(ShowRoleData::fromModel($role));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role, DeleteRoleAction $action)
    {
        Gate::authorize('delete', $role);
        $action->handle($role);
        return response()->noContentJson();
    }
}
