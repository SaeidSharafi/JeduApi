<?php

declare(strict_types=1);

namespace App\Data\Admin\Enrollment;

use App\Enums\ProvisioningProviderEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class ManualProvisioningWaiverData extends Data
{
    public function __construct(public ProvisioningProviderEnum $provider, public string $reason) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return ['provider' => ['required', Rule::enum(ProvisioningProviderEnum::class)], 'reason' => ['required', 'string', 'max:500']];
    }
}
