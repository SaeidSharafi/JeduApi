<?php

declare(strict_types=1);

namespace App\Actions\Admin\Enrollment;

use App\Data\Admin\Enrollment\ManualProvisioningResolutionData;
use App\Data\Admin\Enrollment\ManualProvisioningWaiverData;
use App\Data\Admin\Enrollment\ProvisioningPlanPreviewData;
use App\Enums\ProvisioningProviderEnum;
use App\Models\Enrollment;
use App\Services\Enrollment\ProvisioningPlanResolver;
use App\Services\Provisioning\ProvisioningAttemptService;
use Illuminate\Validation\ValidationException;

final readonly class ManualProvisioningRecoveryAction
{
    public function __construct(private ProvisioningPlanResolver $plans, private ProvisioningAttemptService $attempts) {}

    public function resolve(Enrollment $enrollment, ManualProvisioningResolutionData $data, int $staffId): Enrollment
    {
        $this->assertProvider($enrollment, $data->provider);
        $this->validateReferences($data->provider, $data->references);
        $this->attempts->manuallyResolve($enrollment, $data->provider, $data->references, $data->reason, $staffId);

        return $this->freshEnrollment($enrollment);
    }

    public function waive(Enrollment $enrollment, ManualProvisioningWaiverData $data, int $staffId): Enrollment
    {
        $this->assertProvider($enrollment, $data->provider);
        $this->attempts->waive($enrollment, $data->provider, $data->reason, $staffId);

        return $this->freshEnrollment($enrollment);
    }

    public function preview(Enrollment $enrollment): ProvisioningPlanPreviewData
    {
        $next    = $this->plans->resolve($enrollment->productDeliveryOption);
        $current = collect($enrollment->provisioning_plan['providers'] ?? [])->keyBy('provider');
        $rebuilt = collect($next['providers'])->keyBy('provider');

        return new ProvisioningPlanPreviewData(
            (int) ($enrollment->provisioning_plan['version'] ?? 1),
            ((int) ($enrollment->provisioning_plan['version'] ?? 1)) + 1,
            $rebuilt->keys()->diff($current->keys())->values()->all(),
            $current->keys()->diff($rebuilt->keys())->values()->all(),
            $rebuilt->keys()->intersect($current->keys())->filter(fn (string $key): bool => $rebuilt[$key] !== $current[$key])->values()->all(),
        );
    }

    public function apply(Enrollment $enrollment, bool $confirm, int $staffId): Enrollment
    {
        if (! $confirm) {
            throw ValidationException::withMessages(['confirm' => 'Plan rebuild confirmation is required.']);
        }
        $next                 = $this->plans->resolve($enrollment->productDeliveryOption);
        $old                  = $enrollment->provisioning_plan;
        $data                 = $enrollment->provisioning_data ?? [];
        $history              = $data['plan_history']          ?? [];
        $history[]            = ['version' => $old['version'] ?? 1, 'plan' => $old, 'staff_id' => $staffId, 'applied_at' => now()->toISOString()];
        $data['plan_history'] = $history;
        $next['version']      = ((int) ($old['version'] ?? 1)) + 1;
        $enrollment->forceFill(['provisioning_plan' => $next, 'provisioning_data' => $data]);
        $this->attempts->recalculate($enrollment);
        $enrollment->save();

        return $this->freshEnrollment($enrollment);
    }

    private function assertProvider(Enrollment $enrollment, ProvisioningProviderEnum $provider): void
    {
        if (! collect($enrollment->provisioning_plan['providers'] ?? [])->contains(fn (array $item): bool => ($item['provider'] ?? null) === $provider->value && ($item['applicable'] ?? false) === true)) {
            throw ValidationException::withMessages(['provider' => 'The provider is not part of the canonical enrollment plan.']);
        }
    }

    /** @param array<string, mixed> $references */
    private function validateReferences(ProvisioningProviderEnum $provider, array $references): void
    {
        $valid = match ($provider) {
            ProvisioningProviderEnum::BBB => collect(['meeting_id', 'nili_room_id'])
                ->contains(fn (string $key): bool => is_string($references[$key] ?? null) && filled($references[$key])),
            ProvisioningProviderEnum::SKYROOM                                       => filter_var($references['room_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false,
            ProvisioningProviderEnum::MOODLE, ProvisioningProviderEnum::MOODLE_QUIZ => collect(['moodle_user_id', 'moodle_course_id'])
                ->every(fn (string $key): bool => filter_var($references[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false),
            ProvisioningProviderEnum::IMS => collect(['ims_student_id', 'ims_enrollment_id'])
                ->every(fn (string $key): bool => filter_var($references[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false),
            ProvisioningProviderEnum::SPOTPLAYER => is_string($references['license_key'] ?? null) && filled($references['license_key']),
        };
        if (! $valid) {
            throw ValidationException::withMessages(['references' => 'Provider-specific canonical references are required.']);
        }
    }

    private function freshEnrollment(Enrollment $enrollment): Enrollment
    {
        return $enrollment->fresh(['order.items.vendor', 'order.payments', 'customer', 'productDeliveryOption', 'orderItem.vendor']);
    }
}
