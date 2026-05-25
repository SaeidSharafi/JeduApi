<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Hekmatinasser\Verta\Facades\Verta;
use Illuminate\Support\Carbon;

trait DateUtilTestTrait
{
    public function toJalalitString(null|string|CarbonInterface $value, ?string $format = null): ?string
    {
        if (! $value) {
            return null;
        }

        return verta($value)->format($format ?: config('data.date_output_format'));
    }

    public function parseJalaliDate(?string $value, ?string $format = null): ?string
    {
        if (! $value) {
            return null;
        }

        return Verta::parse($value)->format($format ?: config('data.date_output_format'));
    }

    public function parseGregorianDate(string $value, ?string $format = null): ?string
    {
        return Carbon::parse($value)->format($format ?: config('data.date_output_format'));
    }

    public function jalaliToGregorian(null|Verta|string $value, ?string $format = null): ?string
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof Verta) {
            return $value->toCarbon()->format($format ?: config('data.date_output_format'));
        }

        return Verta::parse($value)->toCarbon()->format($format ?: config('data.date_output_format'));
    }

    public function formatDate(?DateTimeInterface $value, ?string $format = null): ?string
    {
        if (! $value) {
            return null;
        }

        return $value->format($format ?: config('data.date_output_format'));
    }
}
