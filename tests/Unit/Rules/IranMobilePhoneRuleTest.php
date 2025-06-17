<?php

declare(strict_types=1);

use App\Rules\IranMobilePhoneRule;
use Illuminate\Support\Facades\Validator;

describe('IranMobilePhoneRule', function (): void {
    it('only accepts valid mobile numbers of mobileOnly is true', function (): void {
        $rule         = new IranMobilePhoneRule(true);
        $validMobiles = [
            '09123456789',
            '9123456789',
            '09331234567',
            '09991234567',
            '09011234567',
            '09021234567',
            '09031234567',
            '09091234567',
        ];
        $validLandlines = [
            '02123456789',
            '02823456789',
        ];
        foreach ($validMobiles as $mobile) {
            $validator = Validator::make(['mobile' => $mobile], ['mobile' => [$rule]]);
            expect($validator->passes())->toBeTrue();
        }
        foreach ($validLandlines as $landline) {
            $validator = Validator::make(['mobile' => $landline], ['mobile' => [$rule]]);
            expect($validator->fails())->toBeTrue();
        }
    });
    it('accepts valid phone numbers', function (): void {
        $rule              = new IranMobilePhoneRule();
        $validPhoneNumbers = [
            '09123456789', // 09X
            '9123456789',  // 9X (without leading 0)
            '09331234567',
            '09991234567',
            '09011234567',
            '09021234567',
            '09031234567',
            '09091234567',
            '02123456789',
            '02823456789',
        ];
        foreach ($validPhoneNumbers as $mobile) {
            $validator = Validator::make(['mobile' => $mobile], ['mobile' => [$rule]]);
            expect($validator->passes())->toBeTrue();
        }
    });

    it('rejects invalid mobile numbers', function (): void {
        $rule           = new IranMobilePhoneRule();
        $invalidMobiles = [
            'not-a-number',
            '12345',
            '0999123456',  // too short
            '091234567890', // too long
        ];
        foreach ($invalidMobiles as $mobile) {
            $validator = Validator::make(['mobile' => $mobile], ['mobile' => [$rule]]);
            if (! $validator->fails()) {
                fwrite(STDERR, 'Failed to reject invalid: '.var_export($mobile, true)."\n");
            }
            expect($validator->fails())->toBeTrue();
        }
        // Test empty string
        $validator = Validator::make(['mobile' => ''], ['mobile' => ['required', $rule]]);
        expect($validator->fails())->toBeTrue();
        // Test null
        $validator = Validator::make(['mobile' => null], ['mobile' => ['required', $rule]]);
        expect($validator->fails())->toBeTrue();
    });
});
