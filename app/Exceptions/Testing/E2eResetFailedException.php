<?php

declare(strict_types=1);

namespace App\Exceptions\Testing;

use RuntimeException;
use Throwable;

final class E2eResetFailedException extends RuntimeException
{
    public function __construct(
        public readonly string $resetId,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
