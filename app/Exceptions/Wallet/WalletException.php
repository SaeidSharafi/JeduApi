<?php

declare(strict_types=1);

namespace App\Exceptions\Wallet;

use Exception;

abstract class WalletException extends Exception
{
    abstract public function errorCode(): string;

    final public function userMessage(): string
    {
        return $this->customUserMessage() ?? $this->getMessage();
    }

    /**
     * @return array<string, mixed>
     */
    final public function metadata(): array
    {
        return $this->customMetadata();
    }

    protected function customUserMessage(): ?string
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function customMetadata(): array
    {
        return [];
    }
}
