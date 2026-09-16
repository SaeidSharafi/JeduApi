<?php

declare(strict_types=1);

namespace App\Actions\Shop\Student;

use App\Contracts\Integrations\NiliroomClientContract;
use App\Contracts\Integrations\SkyroomClientContract;
use App\Data\Shop\Student\JoinUrlData;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\ProductableEnum;
use App\Exceptions\BundleStructuralInvariantException;
use App\Exceptions\Integrations\ResourceNotProvisionedException;
use App\Models\Enrollment;
use InvalidArgumentException;

final readonly class GetJoinUrlAction
{
    public function __construct(
        private NiliroomClientContract $niliroomService,
        private SkyroomClientContract $skyroomService,
    ) {}

    public function handle(Enrollment $enrollment): JoinUrlData
    {
        $deliveryOption = $enrollment->productDeliveryOption;
        $deliveryMethod = $deliveryOption->delivery_method;
        $provisioning   = $enrollment->provisioning_data['providers'] ?? [];

        $deliveryOption->loadMissing('product');
        if ($deliveryOption->product?->productable_type === ProductableEnum::BUNDLE->value) {
            throw new BundleStructuralInvariantException();
        }

        return match ($deliveryMethod) {
            DeliveryMethodEnum::LIVE_SESSION_BBB     => $this->buildNiliroomJoinUrl($enrollment, $deliveryOption->details_json ?? []),
            DeliveryMethodEnum::LIVE_SESSION_SKYROOM => $this->buildSkyroomJoinUrl($enrollment, $provisioning),
            default                                  => throw new InvalidArgumentException(
                __('messages.enrollment.delivery_no_join_url', ['method' => $deliveryMethod->value])
            ),
        };
    }

    /**
     * Build the student's Niliroom meeting join URL for the delivery option's room.
     *
     * @param  array<string, mixed>  $details
     */
    private function buildNiliroomJoinUrl(Enrollment $enrollment, array $details): JoinUrlData
    {
        $roomId = data_get($details, 'nili_room_id');

        if (! is_string($roomId) || mb_trim($roomId) === '') {
            throw new ResourceNotProvisionedException(__('messages.provisioning.niliroom_room_id_missing'));
        }

        if (! $this->niliroomService->isReady()) {
            throw new ResourceNotProvisionedException(__('messages.enrollments.niliroom_not_configured'));
        }

        $joinUrl = $this->niliroomService->issueStudentMeetingJoinGrant($enrollment->customer, mb_trim($roomId));

        // The panel puts no lifetime on a meeting join grant, so there is no expiry to report.
        return new JoinUrlData(url: $joinUrl, type: 'niliroom');
    }

    /**
     * @param  array<string, mixed>  $provisioning
     */
    private function buildSkyroomJoinUrl(Enrollment $enrollment, array $provisioning): JoinUrlData
    {
        $roomId = data_get($provisioning, 'skyroom.data.room_id');

        if (! $roomId) {
            throw new ResourceNotProvisionedException(__('messages.enrollment.skyroom_not_provisioned'));
        }

        $customer = $enrollment->customer;
        $joinUrl  = $this->skyroomService->createLoginUrl(
            roomId: (int) $roomId,
            userId: 'user-'.$enrollment->customer_id,
            nickname: $customer->full_name ?? 'دانشجو',
        );

        return new JoinUrlData(url: $joinUrl, type: 'skyroom');
    }
}
