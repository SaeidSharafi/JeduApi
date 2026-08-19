<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Contracts\ApiResponseInterface;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

final class RegistrationVelocityExceededException extends Exception
{
    public function render(Request $request): ApiResponseInterface
    {
        return apiResponse()->error(__('messages.auth.register.throttled'), HttpStatus::HTTP_TOO_MANY_REQUESTS);
    }
}
