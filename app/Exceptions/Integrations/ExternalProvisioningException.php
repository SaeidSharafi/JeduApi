<?php

declare(strict_types=1);

namespace App\Exceptions\Integrations;

use JetBrains\PhpStorm\Pure;
use RuntimeException;
use Throwable;

final class ExternalProvisioningException extends RuntimeException
{
    public array $metaData;

    #[Pure]
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, ?array $metaData = [])
    {
        $this->metaData = $metaData;
        parent::__construct($message, $code, $previous);
    }

    public function getMoodleErrorCode(): string
    {
        return data_get($this->metaData, 'errorcode');
    }
}
