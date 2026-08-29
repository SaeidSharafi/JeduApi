<?php

declare(strict_types=1);

namespace App\Http\Controllers\Testing;

use App\Actions\Testing\ResetE2eEnvironmentAction;
use App\Contracts\ApiResponseInterface;
use App\Exceptions\Testing\E2eResetFailedException;
use App\Http\Responses\ApiFailResponse;
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
            try {
                $data = $action->handle();
            } catch (E2eResetFailedException $exception) {
                return new ApiFailResponse(
                    'E2E environment reset failed.',
                    [],
                    Response::HTTP_SERVICE_UNAVAILABLE,
                    [
                        'error_code' => 'E2E_RESET_FAILED',
                        'reset_id'   => $exception->resetId,
                    ],
                );
            }

            return $data === null
                ? apiResponse()->error('Another E2E reset is already in progress.', Response::HTTP_CONFLICT)
                : apiResponse()->success($data, 'Database and Horizon queues reset successfully');
        }

        return apiResponse()->forbidden('Unauthorized environment.');
    }
}
