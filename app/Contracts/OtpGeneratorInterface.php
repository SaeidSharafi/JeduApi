<?php

declare(strict_types=1);

namespace App\Contracts;

interface OtpGeneratorInterface
{
    public function generateCode(): int;

    public function generateTrackingCode(): string;
}
