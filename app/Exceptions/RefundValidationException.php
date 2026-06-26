<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class RefundValidationException extends RuntimeException
{
    public function __construct(
        string $message = 'Refund validation failed.',
        int $code = 422,
    ) {
        parent::__construct($message, $code);
    }
}
