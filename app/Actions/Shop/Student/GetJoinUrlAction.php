<?php

declare(strict_types=1);

namespace App\Actions\Shop\Student;

use App\Contracts\Integrations\BbbClientContract;
use App\Contracts\Integrations\SkyroomClientContract;
use App\Data\Shop\Student\JoinUrlData;
use App\Enums\Product\DeliveryMethodEnum;
use App\Exceptions\Integrations\ResourceNotProvisionedException;
use App\Models\Enrollment;
use InvalidArgumentException;

final readonly class GetJoinUrlAction
{
    public function __construct(
        private BbbClientContract $bbbService,
        private SkyroomClientContract $skyroomService,
    ) {}

    public function handle(Enrollment $enrollment): JoinUrlData
    {
        $deliveryOption = $enrollment->productDeliveryOption;
        $deliveryMethod = $deliveryOption->delivery_method;
        $provisioning   = $enrollment->provisioning_data['providers'] ?? [];

        return match ($deliveryMethod) {
            DeliveryMethodEnum::LIVE_SESSION_BBB     => $this->buildBbbJoinUrl($enrollment, $provisioning),
            DeliveryMethodEnum::LIVE_SESSION_SKYROOM => $this->buildSkyroomJoinUrl($enrollment, $provisioning),
            default                                  => throw new InvalidArgumentException(
                __('messages.enrollment.delivery_no_join_url', ['method' => $deliveryMethod->value])
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $provisioning
     */
    private function buildBbbJoinUrl(Enrollment $enrollment, array $provisioning): JoinUrlData
    {
        $meetingId = data_get($provisioning, 'bbb.data.meeting_id');

        if (! $meetingId) {
            throw new ResourceNotProvisionedException(__('messages.enrollment.bbb_not_provisioned'));
        }

        $joinUrl = $this->bbbService->buildJoinUrl(
            meetingId: (string) $meetingId,
            fullName: $enrollment->customer->full_name ?? 'دانشجو',
        );

        return new JoinUrlData(url: $joinUrl, type: 'bbb');
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
