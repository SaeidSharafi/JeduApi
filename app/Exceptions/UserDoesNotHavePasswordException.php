<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Contracts\ApiResponseInterface;
use App\Http\Responses\ApiFailResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class UserDoesNotHavePasswordException extends Exception
{

    public function render(Request $request): ApiResponseInterface
    {
        return apiResponse()->validationError(__('messages.auth.doesnot_have_password'));
    }
}
