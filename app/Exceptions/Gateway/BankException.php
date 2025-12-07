<?php

declare(strict_types=1);

namespace App\Exceptions\Gateway;

use Exception;

/**
 * This exception when throws, user try to submit a payment request who submitted before
 */
abstract class BankException extends Exception
{
    protected $code = -100;

    protected $message = 'خطای بانک.';
}
