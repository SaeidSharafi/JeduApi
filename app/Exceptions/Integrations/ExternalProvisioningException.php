<?php

declare(strict_types=1);

namespace App\Exceptions\Integrations;

use RuntimeException;
use Throwable;

abstract class ExternalProvisioningException extends RuntimeException
{
    /** @var array<string, mixed> */
    public array $metaData;

    /**
     * @param  array<string, mixed>|null  $metaData
     */
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, ?array $metaData = [])
    {
        $this->metaData = $metaData;
        parent::__construct($message, $code, $previous);
    }

    final public function getMoodleErrorCode(): ?string
    {
        return data_get($this->metaData, 'errorcode');
    }
}
