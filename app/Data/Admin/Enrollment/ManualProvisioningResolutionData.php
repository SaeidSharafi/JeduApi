<?php

declare(strict_types=1);

namespace App\Data\Admin\Enrollment;

use App\Enums\ProvisioningProviderEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class ManualProvisioningResolutionData extends Data
{
    /** @param array<string, mixed> $references */
    public function __construct(
        public ProvisioningProviderEnum $provider,
        public array $references,
        public string $reason,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'provider'   => ['required', Rule::enum(ProvisioningProviderEnum::class)],
            'references' => ['required', 'array'],
            'reason'     => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'provider' => [
                'description' => 'The provisioning provider. Available values: `ims`, `moodle`, `spotplayer`, `bbb`, `skyroom`, `moodle_quiz`.',
                'example'     => ProvisioningProviderEnum::MOODLE->value,
            ],
            'references' => [
                'description' => 'The external references for the provider, keyed by the identifiers that provider uses.',
                'example'     => ['moodle_user_id' => 42],
            ],
            'reason' => [
                'description' => 'The reason for the manual resolution.',
                'example'     => 'Credentials confirmed with vendor.',
            ],
        ];
    }
}
