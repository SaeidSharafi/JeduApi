<?php

declare(strict_types=1);

namespace App\Enums\Sms;

/**
 * Why an SMS attempt was recorded without a provider call.
 *
 * Stored as the `data.reason` value on an `SmsLog::STATUS_SKIPPED` row, so the
 * gateway kill switch, the notification-option gates and a missing credential
 * stay distinguishable in the delivery log.
 */
enum SmsSkipReasonEnum: string
{
    case GATEWAY_DISABLED = 'gateway_disabled';
    case NOT_CONFIGURED   = 'not_configured';
    case OPTION_DISABLED  = 'option_disabled';
    case PATTERN_MISSING  = 'pattern_missing';
    case EMPTY_MESSAGE    = 'empty_message';
}
