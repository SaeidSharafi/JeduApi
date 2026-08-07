<?php

declare(strict_types=1);

namespace App\Exceptions\Gateway;

final class MellatException extends BankException
{
    public int $errorId;

    public function __construct(int|string $errorId)
    {
        $this->errorId = (int) $errorId;

        $message = (string) __('payment_gateways.mellat.error_codes.'.$this->errorId).' #'.$this->errorId;

        parent::__construct($message, $this->errorId);
    }

    public function errorCode(): string
    {
        return 'MELLAT_ERROR_'.$this->errorId;
    }

    /**
     * @return array<string, mixed>
     */
    protected function customMetadata(): array
    {
        return ['gateway' => 'mellat', 'gateway_error_id' => $this->errorId];
    }
}
