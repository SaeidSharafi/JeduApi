<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\OtpGeneratorInterface;
use Illuminate\Support\Str;

final class DefaultOtpGenerator implements OtpGeneratorInterface
{
    public function generateCode(): int
    {
        $min = config('otp.code_min');
        $max = config('otp.code_max');

        return random_int($min, $max);
    }

    public function generateTrackingCode(): string
    {
        return Str::uuid()->toString();
    }
}
