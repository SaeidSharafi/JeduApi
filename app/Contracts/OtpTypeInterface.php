<?php
declare(strict_types=1);

namespace App\Contracts;

interface OtpTypeInterface
{
    public function identifier(): string;
}
