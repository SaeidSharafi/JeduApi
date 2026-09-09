<?php

declare(strict_types=1);

use App\Enums\BundlePurchaseStatusEnum;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\ProvisioningProviderEnum;
use App\Enums\ProvisioningStatusEnum;
use App\Enums\ProvisioningTriggerEnum;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Jobs\Provisioning\ProvisionEnrollmentProviderJob;
use App\Models\BundlePurchase;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use App\Services\Provisioning\Providers\ImsProvisioningProvider;
use App\Services\Provisioning\Providers\MoodleProvisioningProvider;
use App\Services\Provisioning\ProvisioningAttemptService;
use App\Services\Provisioning\ProvisioningProviderRegistry;
use Illuminate\Support\Facades\Queue;

function bundleComponentPair(): array
{
    $customer = User::factory()->create();
    $order    = Order::factory()->create([
        'customer_id' => $customer->id, 'status' => OrderStatusEnum::COMPLETED->value, 'grand_total' => 100000,
    ]);
    Payment::factory()->create([
        'order_id' => $order->id, 'customer_id' => $customer->id, 'amount' => 100000,
        'status'   => PaymentStatusEnum::COMPLETED,
    ]);
    $purchase = BundlePurchase::query()->create([
        'order_id'                   => $order->id,
        'product_delivery_option_id' => ProductDeliveryOption::factory()->create()->id,
        'bundle_name'                => 'Career Bundle', 'product_name' => 'Career', 'name' => 'Complete package',
        'sku'                        => 'BUNDLE-PROV', 'base_value' => 200000, 'selling_price' => 100000,
        'composition_version'        => 1, 'checkout_status' => OrderStatusEnum::COMPLETED->value,
        'product_data_snapshot_json' => [],
    ]);

    $make = fn (string $provider): Enrollment => (function () use ($customer, $order, $purchase, $provider): Enrollment {
        $option = ProductDeliveryOption::factory()->create(['price' => 100000]);
        $item   = OrderItem::factory()->create([
            'order_id'                   => $order->id,
            'bundle_purchase_id'         => $purchase->id,
            'product_delivery_option_id' => $option->id,
            'status'                     => OrderItemStatusEnum::COMPLETED->value,
            'price'                      => 100000, 'total' => 100000,
            'pricing_metadata'           => ['paid_amount' => 100000, 'total_discount_amount' => 0],
        ]);
        $enrollment = Enrollment::factory()->create([
            'order_id'                   => $order->id,
            'order_item_id'              => $item->id,
            'customer_id'                => $customer->id,
            'product_delivery_option_id' => $option->id,
            'enrollment_status'          => EnrollmentStatusEnum::ACTIVE->value,
        ]);
        $enrollment->update([
            'provisioning_plan' => [
                'version'   => 1,
                'providers' => [['provider' => $provider, 'applicable' => true, 'readiness' => 'ready']],
                'status'    => ProvisioningStatusEnum::READY->value,
            ],
            'provisioning_status' => ProvisioningStatusEnum::READY->value,
        ]);

        return $enrollment->fresh();
    })();

    return [$purchase, $make('moodle'), $make('ims')];
}

/** @return array{0: ProvisioningAttempt, 1: Enrollment} */
function runBundleAttempt(Enrollment $enrollment, ProvisioningProviderEnum $provider): array
{
    $attempts = app(ProvisioningAttemptService::class);
    $attempt  = $attempts->queue($enrollment, ProvisioningTriggerEnum::PAYMENT, provider: $provider);
    (new ProvisionEnrollmentProviderJob($attempt->id))->handle($attempts, app(ProvisioningProviderRegistry::class));

    return [$attempt->refresh(), $enrollment->fresh()];
}

it('provisions every Bundle component independently and reports the purchase active when all succeed', function (): void {
    Queue::fake();
    [$purchase, $moodleEnrollment, $imsEnrollment] = bundleComponentPair();

    $moodle = $this->mock(MoodleProvisioningProvider::class);
    $moodle->shouldReceive('provision')->once()->andReturn(['moodle_user_id' => 1, 'moodle_course_id' => 10]);
    $ims = $this->mock(ImsProvisioningProvider::class);
    $ims->shouldReceive('provision')->once()->andReturn(['course_code' => 'X', 'ims_student_id' => 2, 'ims_enrollment_id' => 20]);

    [$moodleAttempt, $moodleEnrollment] = runBundleAttempt($moodleEnrollment, ProvisioningProviderEnum::MOODLE);
    [$imsAttempt, $imsEnrollment]       = runBundleAttempt($imsEnrollment, ProvisioningProviderEnum::IMS);

    expect($moodleAttempt->status->value)->toBe('succeeded')
        ->and($imsAttempt->status->value)->toBe('succeeded')
        ->and($moodleEnrollment->provisioning_status)->toBe(ProvisioningStatusEnum::HEALTHY)
        ->and($imsEnrollment->provisioning_status)->toBe(ProvisioningStatusEnum::HEALTHY)
        ->and($purchase->fresh()->status)->toBe(BundlePurchaseStatusEnum::ACTIVE);
});

it('keeps a successful component active while another fails, and retrying the failed one is idempotent', function (): void {
    Queue::fake();
    [$purchase, $moodleEnrollment, $imsEnrollment] = bundleComponentPair();

    $moodle = $this->mock(MoodleProvisioningProvider::class);
    $moodle->shouldReceive('provision')->once()->andReturn(['moodle_user_id' => 1, 'moodle_course_id' => 10]);
    $ims = $this->mock(ImsProvisioningProvider::class);
    $ims->shouldReceive('provision')->once()->andThrow(new UnrecoverableProvisioningException('ims down'));

    [$moodleAttempt, $moodleEnrollment] = runBundleAttempt($moodleEnrollment, ProvisioningProviderEnum::MOODLE);

    $attempts      = app(ProvisioningAttemptService::class);
    $failedAttempt = $attempts->queue($imsEnrollment, ProvisioningTriggerEnum::PAYMENT, provider: ProvisioningProviderEnum::IMS);
    $job           = new ProvisionEnrollmentProviderJob($failedAttempt->id);
    $failedJob     = false;
    try {
        $job->handle($attempts, app(ProvisioningProviderRegistry::class));
    } catch (UnrecoverableProvisioningException) {
        $failedJob = true;
    }
    expect($failedJob)->toBeTrue()
        ->and($failedAttempt->refresh()->status->value)->toBe('manual_action_required')
        ->and($imsEnrollment->fresh()->provisioning_status)->toBe(ProvisioningStatusEnum::MANUAL_ACTION_REQUIRED)
        ->and($moodleEnrollment->fresh()->provisioning_status)->toBe(ProvisioningStatusEnum::HEALTHY)
        ->and($moodleEnrollment->fresh()->enrollment_status)->toBe(EnrollmentStatusEnum::ACTIVE)
        ->and($purchase->fresh()->status)->toBe(BundlePurchaseStatusEnum::PARTIALLY_FAILED);

    $dataBefore = $moodleEnrollment->fresh()->provisioning_data;

    // Retry ONLY the failed component; the successful one must not be rerun.
    $ims->shouldReceive('provision')->once()->andReturn(['course_code' => 'X', 'ims_student_id' => 2, 'ims_enrollment_id' => 20]);
    [$retriedAttempt, $retriedEnrollment] = runBundleAttempt($imsEnrollment, ProvisioningProviderEnum::IMS);

    expect($retriedAttempt->status->value)->toBe('succeeded')
        ->and($retriedEnrollment->provisioning_status)->toBe(ProvisioningStatusEnum::HEALTHY)
        ->and($purchase->fresh()->status)->toBe(BundlePurchaseStatusEnum::ACTIVE)
        ->and($moodleEnrollment->fresh()->provisioning_data)->toBe($dataBefore)
        ->and($moodleEnrollment->provisioningAttempts()->count())->toBe(1);
});

it('reports the purchase failed when every component provisioning fails', function (): void {
    Queue::fake();
    [$purchase, $moodleEnrollment, $imsEnrollment] = bundleComponentPair();

    $moodle = $this->mock(MoodleProvisioningProvider::class);
    $moodle->shouldReceive('provision')->andThrow(new UnrecoverableProvisioningException('moodle down'));
    $ims = $this->mock(ImsProvisioningProvider::class);
    $ims->shouldReceive('provision')->andThrow(new UnrecoverableProvisioningException('ims down'));

    foreach ([[$moodleEnrollment, ProvisioningProviderEnum::MOODLE], [$imsEnrollment, ProvisioningProviderEnum::IMS]] as [$enrollment, $provider]) {
        $attempts = app(ProvisioningAttemptService::class);
        $attempt  = $attempts->queue($enrollment, ProvisioningTriggerEnum::PAYMENT, provider: $provider);
        try {
            (new ProvisionEnrollmentProviderJob($attempt->id))->handle($attempts, app(ProvisioningProviderRegistry::class));
        } catch (UnrecoverableProvisioningException) {
            // expected
        }
    }

    expect($moodleEnrollment->fresh()->provisioning_status)->toBe(ProvisioningStatusEnum::MANUAL_ACTION_REQUIRED)
        ->and($imsEnrollment->fresh()->provisioning_status)->toBe(ProvisioningStatusEnum::MANUAL_ACTION_REQUIRED)
        ->and($purchase->fresh()->status)->toBe(BundlePurchaseStatusEnum::FAILED);
});
