<?php

declare(strict_types=1);

namespace App\Services\Payment\Digipay;

/**
 * Payment status codes returned by Digipay.
 */
final class DigipayPaymentStatus
{
    public const int SUCCESS = 0;

    public const int  INVALID_INPUT = 1054;

    public const int  NOT_FOUND = 9000;

    public const int  INVALID_TOKEN = 9001;

    public const int  EXPIRED = 9003;

    public const int  IN_PROGRESS = 9004;

    public const int  NOT_PAYABLE = 9005;

    public const int  PSP_ERROR = 9006;

    public const int  PAYMENT_FAILED = 9007;

    public const int  DUPLICATE_DIFFERENT_DATA = 9008;

    public const int  VERIFY_TIMEOUT = 9009;

    public const int  VERIFY_FAILED = 9010;

    public const int  VERIFY_INDETERMINATE = 9011;

    public const int  INVALID_STATE = 9012;

    public const int  CELL_REQUIRED = 9030;

    public const int  TICKET_NOT_POSSIBLE = 9031;

    /**
     * Get human-readable message for status code.
     */
    public static function getMessage(int $code, ?string $default = null): string
    {
        $key        = 'payment_gateways.digipay.status.'.$code;
        $translated = __($key);

        if ($translated === $key) {
            return $default ?? (string) __('messages.error');
        }

        return $translated;
    }

    /**
     * Check if status indicates success.
     */
    public static function isSuccess(int $code): bool
    {
        return $code === self::SUCCESS;
    }
}
