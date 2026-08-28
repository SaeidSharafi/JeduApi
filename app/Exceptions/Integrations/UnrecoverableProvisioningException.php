<?php

declare(strict_types=1);

namespace App\Exceptions\Integrations;

final class UnrecoverableProvisioningException extends ExternalProvisioningException
{
    public function getValidationErrors(): mixed
    {
        return data_get($this->metaData, 'validation_errors', []);
    }
}
