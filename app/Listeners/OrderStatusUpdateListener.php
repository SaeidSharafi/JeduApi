<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\Order\OrderStatusEnum;
use App\Enums\ProvisioningProviderEnum;
use App\Enums\ProvisioningTriggerEnum;
use App\Events\OrderStatusUpdatedEvent;
use App\Jobs\Provisioning\ProvisionBbbEnrollmentJob;
use App\Jobs\Provisioning\ProvisionEnrollmentProviderJob;
use App\Jobs\Provisioning\ProvisionSkyroomEnrollmentJob;
use App\Models\Order;
use App\Services\Enrollment\ProvisioningPlanResolver;
use App\Services\Provisioning\ProvisioningAttemptService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class OrderStatusUpdateListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private readonly ProvisioningPlanResolver $planResolver,
        private readonly ProvisioningAttemptService $attemptService,
    ) {}

    public function handle(OrderStatusUpdatedEvent $event): void
    {
        /** @var ?Order $order */
        $order = $event->order->fresh();
        if (! $order) {
            return;
        }
        if ($order->status !== OrderStatusEnum::COMPLETED) {
            return;
        }

        $order = $order->load([
            'customer',
            'firstPayment',
            'items' => fn ($q) => $q->with('enrollment', 'productDeliveryOption'),
        ]);

        foreach ($order->items as $item) {
            if (! $item->enrollment || ! $item->productDeliveryOption) {
                continue;
            }

            $plan = $item->enrollment->provisioning_plan;
            if (! is_array($plan) || ! array_key_exists('version', $plan)) {
                $plan = $this->planResolver->resolve($item->productDeliveryOption);
                $item->enrollment->forceFill([
                    'provisioning_plan'   => $plan,
                    'provisioning_status' => $plan['status'],
                ])->saveQuietly();
            }

            $plannedProviders = collect($plan['providers'] ?? [])->pluck('provider');

            if ($plannedProviders->isEmpty()) {
                $item->enrollment->activateIfNoProvisioningRequired();
            }

            if ($plannedProviders->contains('ims')) {
                $attempt = $this->attemptService->queue($item->enrollment, ProvisioningTriggerEnum::PAYMENT, provider: ProvisioningProviderEnum::IMS);
                ProvisionEnrollmentProviderJob::dispatch($attempt->id);
            }

            if ($plannedProviders->contains('moodle')) {
                $attempt = $this->attemptService->queue($item->enrollment, ProvisioningTriggerEnum::PAYMENT);
                ProvisionEnrollmentProviderJob::dispatch($attempt->id);
            }

            if ($plannedProviders->contains('spotplayer') && $this->isProviderReady($plan, 'spotplayer')) {
                $attempt = $this->attemptService->queue($item->enrollment, ProvisioningTriggerEnum::PAYMENT, provider: ProvisioningProviderEnum::SPOTPLAYER);
                ProvisionEnrollmentProviderJob::dispatch($attempt->id);
            }

            if ($plannedProviders->contains('bbb')) {
                ProvisionBbbEnrollmentJob::dispatch($item->enrollment->id);
            }

            if ($plannedProviders->contains('skyroom')) {
                ProvisionSkyroomEnrollmentJob::dispatch($item->enrollment->id);
            }

            if ($plannedProviders->contains('moodle_quiz') && $this->isProviderReady($plan, 'moodle_quiz')) {
                $attempt = $this->attemptService->queue($item->enrollment, ProvisioningTriggerEnum::PAYMENT, provider: ProvisioningProviderEnum::MOODLE_QUIZ);
                ProvisionEnrollmentProviderJob::dispatch($attempt->id);
            }
        }
    }

    /** @param array<string, mixed> $plan */
    private function isProviderReady(array $plan, string $provider): bool
    {
        return collect($plan['providers'] ?? [])->contains(
            fn (array $planned): bool => ($planned['provider'] ?? null) === $provider
                && ($planned['readiness'] ?? null)                      === 'ready'
                && ($planned['applicable'] ?? false)                    === true,
        );
    }
}
