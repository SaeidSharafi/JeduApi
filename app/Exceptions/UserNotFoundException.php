<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class UserNotFoundException extends NotFoundHttpException
{
    public function __construct(string $message = '')
    {
        parent::__construct($message ?: __('messages.auth.login.not_found'));
    }
}
