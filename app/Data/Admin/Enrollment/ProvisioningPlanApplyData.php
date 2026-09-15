<?php

declare(strict_types=1);

namespace App\Data\Admin\Enrollment;

use Spatie\LaravelData\Data;

final class ProvisioningPlanApplyData extends Data
{
    public function __construct(public bool $confirm = false) {}

    public static function rules(): array
    {
        return ['confirm' => ['accepted']];
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'confirm' => [
                'description' => 'Set to true to confirm and apply the plan.',
                'example'     => true,
            ],
        ];
    }
}
