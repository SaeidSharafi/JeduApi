<?php

declare(strict_types=1);

use App\Rules\EmailOrPhoneRule;
use Illuminate\Support\Facades\Validator;

describe('EmailOrPhoneRule', function () {
    it('accepts valid email addresses', function () {
        $rule = new EmailOrPhoneRule();
        $emails = [
            'user@example.com',
            'test.user+alias@domain.co',
            'foo.bar@baz.io',
        ];
        foreach ($emails as $email) {
            $validator = Validator::make(['contact' => $email], ['contact' => [$rule]]);
            expect($validator->passes())->toBeTrue();
        }
    });

    it('accepts valid mobile numbers', function () {
        $rule = new EmailOrPhoneRule();
        $mobiles = [
            '09123456789', // 09X
            '9123456789',  // 9X (without leading 0)
            '09331234567',
            '09991234567',
        ];
        foreach ($mobiles as $mobile) {
            $validator = Validator::make(['contact' => $mobile], ['contact' => [$rule]]);
            expect($validator->passes())->toBeTrue();
        }
    });

    it('accepts valid landline numbers', function () {
        $rule = new EmailOrPhoneRule();
        $landlines = [
            '02112345678',
            '03112345678',
            '08112345678',
        ];
        foreach ($landlines as $landline) {
            $validator = Validator::make(['contact' => $landline], ['contact' => [$rule]]);
            expect($validator->passes())->toBeTrue();
        }
    });

    it('rejects invalid values', function () {
        $rule = new EmailOrPhoneRule();
        $invalids = [
            'not-an-email',
            '12345',
            '0999123456',  // too short
            'user@',
            '@domain.com',
            '091234567890', // too long
        ];
        foreach ($invalids as $invalid) {
            $validator = Validator::make(['contact' => $invalid], ['contact' => [$rule]]);
            expect($validator->fails())->toBeTrue();
        }
    });

    it('rejects null values as invalid', function () {
        $rule = new EmailOrPhoneRule();
        $validator = Validator::make(['contact' => null], ['contact' => ['required', $rule]]);
        expect($validator->fails())->toBeTrue();
    });
});
