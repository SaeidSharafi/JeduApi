<?php

declare(strict_types=1);

use App\Enums\Sms\SmsNotificationOptionEnum;

covers(SmsNotificationOptionEnum::class);

describe('SmsNotificationOptionEnum', function (): void {
    it('maps every option to the outgoing message type it governs', function (): void {
        expect(SmsNotificationOptionEnum::OTP->logType())->toBe('OTP')
            ->and(SmsNotificationOptionEnum::REFUND_COMPLETED->logType())->toBe('REFUND')
            ->and(SmsNotificationOptionEnum::ORDER_PAID->logType())->toBe('ORDER')
            ->and(SmsNotificationOptionEnum::ENROLLMENT_READY->logType())->toBe('ENROLLMENT')
            ->and(SmsNotificationOptionEnum::WALLET_CAMPAIGN_CREDITED->logType())->toBe('WALLET');
    });

    it('resolves every message type back to its option', function (): void {
        foreach (SmsNotificationOptionEnum::cases() as $option) {
            expect(SmsNotificationOptionEnum::fromLogType($option->logType()))->toBe($option);
        }
    });

    it('returns null for a message type with no configurable option', function (): void {
        expect(SmsNotificationOptionEnum::fromLogType('GREETING'))->toBeNull();
    });

    it('treats only the refund as configured without a pattern', function (): void {
        $withoutPattern = ['enabled' => true, 'pattern_code' => ''];
        $withPattern    = ['enabled' => true, 'pattern_code' => 'code'];

        expect(SmsNotificationOptionEnum::REFUND_COMPLETED->requiresPattern())->toBeFalse()
            ->and(SmsNotificationOptionEnum::OTP->requiresPattern())->toBeTrue()
            ->and(SmsNotificationOptionEnum::REFUND_COMPLETED->isConfigured($withoutPattern))->toBeTrue()
            ->and(SmsNotificationOptionEnum::OTP->isConfigured($withoutPattern))->toBeFalse()
            ->and(SmsNotificationOptionEnum::OTP->isConfigured($withPattern))->toBeTrue();
    });
});
