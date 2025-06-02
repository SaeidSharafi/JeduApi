<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Role\OutputPermissionsAction;
use App\Data\Role\PermissionData;
use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Permission;

class PermissonController extends Controller
{
    public function __invoke(OutputPermissionsAction $action)
    {
        return response()->success($action->handle());
    }
}
