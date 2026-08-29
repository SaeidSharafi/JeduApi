<?php

declare(strict_types=1);

namespace App\Http\Controllers\Testing;

use App\Actions\Testing\ResetE2eEnvironmentAction;
use App\Contracts\ApiResponseInterface;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

final class TestingDatabaseResetController extends Controller
{
    public function reset(Request $request, ResetE2eEnvironmentAction $action): ApiResponseInterface
    {
        if (
            app()->environment('e2e')
            && (string) config('e2e.control_key') !== ''
            && hash_equals((string) config('e2e.control_key'), (string) $request->header('X-E2E-Key'))
        ) {
            $data = $action->handle();

            return $data === null
                ? apiResponse()->error('Another E2E reset is already in progress.', Response::HTTP_CONFLICT)
                : apiResponse()->success($data, 'Database and Horizon queues reset successfully');
        }

        return apiResponse()->forbidden('Unauthorized environment.');
    }
}
