<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Payment;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Payment\DigipayInquireRefundRequestData;
use App\Data\Admin\Payment\DigipayRefundRequestData;
use App\Exceptions\Gateway\DigipayException;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payment\Digipay\DigipayAdminService;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Digipay Operations
 *
 * @authenticated
 */
final class DigipayAdminController extends Controller
{
    public function __construct(
        private readonly DigipayAdminService $service,
    ) {}

    /**
     * Refund a Digipay payment.
     *
     * @responseFile resources/responses/admin/payment/digipay-refund.json
     */
    public function refund(Payment $payment, DigipayRefundRequestData $data): ApiResponseInterface
    {
        Gate::authorize('refund', $payment);

        try {
            $response = $this->service->refund($payment, $data->amount);

            return apiResponse()->success([
                'tracking_code' => $response->trackingCode,
                'message'       => $response->message,
            ]);
        } catch (DigipayException $e) {
            return apiResponse()->error($e->getUserMessage(), 422, ['digipay_code' => $e->getDigipayCode()]);
        }
    }

    /**
     * Confirm delivery of a Digipay payment.
     *
     * @responseFile resources/responses/admin/payment/digipay-deliver.json
     */
    public function deliver(Payment $payment): ApiResponseInterface
    {
        Gate::authorize('deliver', $payment);

        if (! $this->service->requiresDeliveryConfirmation($payment)) {
            return apiResponse()->error(__('messages.digipay.delivery_not_required'), 422);
        }

        try {
            $response = $this->service->deliver($payment);

            return apiResponse()->success(['message' => $response->message]);
        } catch (DigipayException $e) {
            return apiResponse()->error($e->getUserMessage(), 422, ['digipay_code' => $e->getDigipayCode()]);
        }
    }

    /**
     * Reverse a Digipay payment within the 25-minute window.
     *
     * @responseFile resources/responses/admin/payment/digipay-reverse.json
     */
    public function reverse(Payment $payment): ApiResponseInterface
    {
        Gate::authorize('reverse', $payment);

        $latestTx = $payment->transactions()->latest()->first();
        if ($latestTx && $latestTx->created_at->addMinutes(25)->isPast()) {
            return apiResponse()->error(__('messages.digipay.reverse_window_expired'), 422);
        }

        try {
            $response = $this->service->reverse($payment);

            return apiResponse()->success([
                'tracking_code' => $response->trackingCode,
                'amount'        => $response->amount,
                'message'       => $response->message,
            ]);
        } catch (DigipayException $e) {
            return apiResponse()->error($e->getUserMessage(), 422, ['digipay_code' => $e->getDigipayCode()]);
        }
    }

    /**
     * Inquire about the status of a Digipay refund.
     *
     * @responseFile resources/responses/admin/payment/digipay-inquire-refund.json
     */
    public function inquireRefund(DigipayInquireRefundRequestData $data): ApiResponseInterface
    {
        Gate::authorize('inquire', Payment::class);

        try {
            $response = $this->service->inquireRefund($data->refund_provider_id, $data->type);

            return apiResponse()->success([
                'status' => match (true) {
                    $response->isRefundCompleted() => 'completed',
                    $response->isRefundFailed()    => 'failed',
                    $response->isRefundPending()   => 'pending',
                    default                        => 'unknown',
                },
                'tracking_code' => $response->trackingCode,
                'transfer_date' => $response->transferDate,
                'destination'   => $response->destination,
            ]);
        } catch (DigipayException $e) {
            return apiResponse()->error($e->getUserMessage(), 422, ['digipay_code' => $e->getDigipayCode()]);
        }
    }
}
