<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Contracts\ApiResponseInterface;
use Exception;
use Illuminate\Http\Request;

final class UserDoesNotHavePasswordException extends Exception
{
    public function render(Request $request): ApiResponseInterface
    {
        return apiResponse()->validationError(__('messages.auth.doesnot_have_password'));
    }
}
