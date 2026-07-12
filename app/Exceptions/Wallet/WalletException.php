<?php

namespace App\Exceptions\Wallet;

abstract class WalletException extends \Exception
{
    abstract public function errorCode(): string;

    final public function userMessage(): string
    {
        return $this->customUserMessage() ?? $this->getMessage();
    }

    final public function metadata(): array
    {
        return $this->customMetadata();
    }

    protected function customUserMessage(): ?string
    {
        return null;
    }

    protected function customMetadata(): array
    {
        return [];
    }
}
