<?php

declare(strict_types=1);

namespace App\Actions\Shop\Payment;

use App\Contracts\Payment\PaymentExceptionContract;
use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Data\Shop\Cart\CheckoutResponseData;
use App\Data\Shop\Payment\PaymentInitiationResponseData;
use App\Data\Shop\Payment\PaymentResponseData;
use App\Data\Shop\Payment\PaymentSummaryData;
use App\Data\Shop\Student\Order\OrderData;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Exceptions\Gateway\DigipayException;
use App\Exceptions\Gateway\MellatException;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use LogicException;
use SoapFault;
use Throwable;

final class BuildPaymentResponseAction
{
    public function checkoutResponse(PaymentProcessResultData $result): CheckoutResponseData
    {
        $order = $result->payment->order?->fresh(['payments.transactions'])
            ?? throw new LogicException('Checkout payment must belong to an order.');

        return new CheckoutResponseData(
            order: OrderData::fromModel($order),
            redirect_url: $result->redirect_url,
            redirect_data: $result->redirect_data,
            redirect_method: $result->redirect_method,
            requires_redirect: $result->requiresRedirect(),
        );
    }

    public function initiationResponse(PaymentProcessResultData $result): PaymentInitiationResponseData
    {
        $payment = $result->payment;

        return new PaymentInitiationResponseData(
            payment: new PaymentSummaryData(
                id: $payment->id,
                uuid: $payment->uuid,
                amount: $payment->amount,
                method: $payment->method?->value,
                status: $payment->status?->value,
                purpose: $payment->purpose?->value,
                last_gateway_reference: $payment->last_gateway_reference,
                attempt_count: $payment->attempt_count ?? 0,
            ),
            requires_redirect: $result->requiresRedirect(),
            redirect_url: $result->redirect_url,
            redirect_data: $result->redirect_data,
            redirect_method: $result->redirect_method,
        );
    }

    public function initiationMessage(PaymentProcessResultData $result): string
    {
        return $this->message($this->handle(payment: $result->payment), requiresRedirect: $result->requiresRedirect());
    }

    public function validationFailure(Request $request, ValidationException $exception): ?PaymentResponseData
    {
        if (! $request->routeIs('api.v1.shop.checkout', 'api.v1.shop.wallet.topup', 'api.v1.shop.student.orders.retry-payment')) {
            return null;
        }

        $order = $request->routeIs('api.v1.shop.student.orders.retry-payment')
            ? $request->user('user')?->orders()->where('increment_id', $request->route('increment_id'))->first()
            : null;

        return $this->handle(exception: $exception, order: $order);
    }

    public function handle(
        ?Payment $payment = null,
        ?Throwable $exception = null,
        ?Order $order = null,
    ): PaymentResponseData {
        $order ??= $payment?->order;
        $order     = $order?->fresh();
        $status    = $this->outcome($payment, $exception);
        $errorCode = match (true) {
            $status === 'successful'                       => null,
            $exception instanceof PaymentExceptionContract => $exception->errorCode(),
            $exception instanceof ValidationException      => 'PAYMENT_VALIDATION_FAILED',
            $exception !== null                            => 'UNKNOWN_ERROR',
            $status === 'failed'                           => 'PAYMENT_FAILED',
            default                                        => null,
        };

        return new PaymentResponseData(
            status: $status,
            order_id: $order?->increment_id,
            payment_id: $payment?->uuid,
            error_code: $errorCode,
            can_retry: $status     === 'failed'
                && $order?->status === OrderStatusEnum::PENDING
                && $order->balance_due > 0,
        );
    }

    public function message(PaymentResponseData $data, ?Throwable $exception = null, bool $requiresRedirect = false): string
    {
        return match ($data->status) {
            'successful' => __('messages.payment.completed_successfully'),
            'pending'    => $requiresRedirect
                ? __('messages.payment.initiated')
                : __('messages.wallet.payment_pending_verification'),
            'failed' => $exception instanceof PaymentExceptionContract
                ? $exception->userMessage()
                : __('messages.action_failed'),
            default => __('messages.server_error'),
        };
    }

    private function outcome(?Payment $payment, ?Throwable $exception): string
    {
        if ($payment?->status === PaymentStatusEnum::COMPLETED) {
            return 'successful';
        }

        if ($exception !== null) {
            for ($cause = $exception; $cause !== null; $cause = $cause->getPrevious()) {
                if ($cause instanceof ConnectionException || $cause instanceof SoapFault) {
                    return 'unknown';
                }
            }

            // Gateway exceptions with no provider code can represent transport failures.
            if (($exception instanceof MellatException && $exception->errorId <= 0)
                || ($exception instanceof DigipayException && ($exception->getDigipayCode() <= 0 || $exception->getDigipayCode() === 408 || ($exception->getDigipayCode() >= 500 && $exception->getDigipayCode() <= 599)))) {
                return 'unknown';
            }

            return $exception instanceof PaymentExceptionContract || $exception instanceof ValidationException
                ? 'failed'
                : 'unknown';
        }

        return $payment?->status === PaymentStatusEnum::FAILED ? 'failed' : 'pending';
    }
}
