<?php

declare(strict_types=1);

use App\Helpers\PhoneNumberHelper;

describe('PhoneNumberHelper', function (): void {
    it('normalizes a phone number missing the leading zero', function (): void {
        expect(PhoneNumberHelper::normalize('9351234567'))->toBe('09351234567');
    });

    it('preserves a phone number that already has the leading zero', function (): void {
        expect(PhoneNumberHelper::normalize('09351234567'))->toBe('09351234567');
    });

    it('collapses multiple leading zeros to a single one', function (): void {
        expect(PhoneNumberHelper::normalize('009351234567'))->toBe('09351234567');
    });

    it('strips the leading zero for the canonical comparison form', function (): void {
        expect(PhoneNumberHelper::withoutLeadingZero('09351234567'))->toBe('9351234567')
            ->and(PhoneNumberHelper::withoutLeadingZero('9351234567'))->toBe('9351234567');
    });

    it('returns lookup variants for both with and without leading zero', function (): void {
        expect(PhoneNumberHelper::lookupVariants('09351234567'))->toBe(['9351234567', '09351234567'])
            ->and(PhoneNumberHelper::lookupVariants('9351234567'))->toBe(['9351234567', '09351234567']);
    });
});
