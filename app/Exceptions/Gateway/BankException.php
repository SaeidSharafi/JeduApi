<?php

declare(strict_types=1);

namespace App\Exceptions\Gateway;

use App\Exceptions\Payment\PaymentException;
use Exception;
use Throwable;

/**
 * This exception when throws, user try to submit a payment request who submitted before
 */
abstract class BankException extends PaymentException
{
    /** @var int */
    protected $code = -100;

    protected $message;

    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        if ($message === '') {
            $message = (string) __('messages.bank_error');
        }

        parent::__construct($message, $code ?: $this->code, $previous);
    }
}
