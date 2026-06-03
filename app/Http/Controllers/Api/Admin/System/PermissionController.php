<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\System;

use App\Actions\Admin\Role\OutputPermissionsAction;
use App\Contracts\ApiResponseInterface;
use App\Http\Controllers\Controller;

/**
 * @group Admin - Permissions
 *
 * APIs for getting all permissions
 */
final class PermissionController extends Controller
{
    /**
     * Get all permissions
     *
     * @responseFile 200 resources/responses/permissions.json
     */
    public function __invoke(OutputPermissionsAction $action): ApiResponseInterface
    {
        return response()->success($action->handle());
    }
}
