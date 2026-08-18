<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Contracts\ApiResponseInterface;
use Exception;
use Illuminate\Http\Request;

final class UserBannedException extends Exception
{
    public function render(Request $request): ApiResponseInterface
    {
        return apiResponse()->forbidden(__('messages.auth.login.banned'));
    }
}
