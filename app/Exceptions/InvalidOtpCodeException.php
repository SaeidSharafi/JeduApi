<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Contracts\ApiResponseInterface;
use Exception;
use Illuminate\Http\Request;

final class InvalidOtpCodeException extends Exception
{
    public function render(Request $request): ApiResponseInterface
    {
        return apiResponse()->validationError(__('messages.auth.otp.invalid_code'));
    }
}
