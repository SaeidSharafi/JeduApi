<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Actions\Shop\Payment\BuildPaymentResponseAction;
use App\Contracts\ApiResponseInterface;
use App\Contracts\Payment\PaymentExceptionContract;
use App\Exceptions\Gateway\BankException;
use App\Exceptions\Gateway\DigipayException;
use App\Exceptions\Wallet\WalletInsufficientBalanceException;
use App\Models\Order;
use App\Models\Payment;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use SoapFault;
use Throwable;

final class ShopPaymentProcessingException extends Exception
{
    public function __construct(public readonly ?Payment $payment, public readonly Throwable $cause, public readonly ?Order $order = null)
    {
        parent::__construct('Shop payment processing failed.', previous: $cause);
    }

    public function render(Request $request): ApiResponseInterface
    {
        $responseAction   = app(BuildPaymentResponseAction::class);
        $data             = $responseAction->handle(payment: $this->payment, exception: $this->cause, order: $this->order);
        $transportFailure = false;
        for ($cause = $this->cause; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof ConnectionException || $cause instanceof SoapFault) {
                $transportFailure = true;
                break;
            }
        }
        $status = match (true) {
            $transportFailure                                                                                      => 504,
            $this->cause instanceof DigipayException && in_array($this->cause->getDigipayCode(), [408, 504], true) => 504,
            $this->cause instanceof BankException                                                                  => 502,
            $this->cause instanceof PaymentExceptionContract,
            $this->cause instanceof ValidationException => 409,
            default                                     => 500,
        };
        $errors = match (true) {
            $this->cause instanceof WalletInsufficientBalanceException => ['wallet_balance' => $this->cause->getMessage()],
            $this->cause instanceof ValidationException                => $this->cause->errors(),
            default                                                    => null,
        };

        return apiResponse()->error(
            message: $responseAction->message($data, $this->cause),
            status: $status,
            errors: $errors,
            data: $data,
            metadata: $this->cause instanceof PaymentExceptionContract ? Arr::except($this->cause->metadata(), ['error_code', 'order_id', 'payment_id']) : [],
        );
    }
}
