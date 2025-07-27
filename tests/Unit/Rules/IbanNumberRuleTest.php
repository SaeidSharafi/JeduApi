<?php

declare(strict_types=1);

use App\Rules\IbanNumberRule;
use Illuminate\Validation\Validator;
use Illuminate\Validation\ValidationException;

describe('IbanNumberRule', function () {
    it('accepts valid Iranian IBAN in production', function () {
        $rule = new IbanNumberRule();
        $iban = 'IR062960000000100324200001';
        app()->detectEnvironment(fn() => 'production');
        $validator = validator(['iban' => $iban], ['iban' => [$rule]]);
        $thrown = false;
        try {
            $validator->validate();
        } catch (ValidationException $e) {
            $thrown = true;
        }
        expect($thrown)->toBeFalse();
    });

    it('accepts valid Iranian IBAN in non-production (skips MOD 97)', function () {
        $rule = new IbanNumberRule();
        $iban = 'IR062960000000100324200001';
        app()->detectEnvironment(fn() => 'local');
        $validator = validator(['iban' => $iban], ['iban' => [$rule]]);
        $thrown = false;
        try {
            $validator->validate();
        } catch (ValidationException $e) {
            $thrown = true;
        }
        expect($thrown)->toBeFalse();
    });

    it('rejects IBAN with wrong length', function () {
        $rule = new IbanNumberRule();
        $iban = 'IR0629600000001003242000'; // 25 chars
        $validator = validator(['iban' => $iban], ['iban' => [$rule]]);
        expect(fn() => $validator->validate())->toThrow(ValidationException::class);
    });

    it('rejects IBAN with wrong prefix', function () {
        $rule = new IbanNumberRule();
        $iban = 'FR062960000000100324200001'; // Not IR
        $validator = validator(['iban' => $iban], ['iban' => [$rule]]);
        expect(fn() => $validator->validate())->toThrow(ValidationException::class);
    });

    it('rejects IBAN with non-numeric body', function () {
        $rule = new IbanNumberRule();
        $iban = 'IR06A960000000100324200001'; // Contains A
        $validator = validator(['iban' => $iban], ['iban' => [$rule]]);
        expect(fn() => $validator->validate())->toThrow(ValidationException::class);
    });

    it('rejects empty IBAN', function () {
        $rule = new IbanNumberRule();
        $validator = validator(['iban' => 'as'], ['iban' => ['nullable',$rule]]);
        expect(fn() => $validator->validate())->toThrow(ValidationException::class);
    });

    it('rejects IBAN with invalid MOD 97 in production', function () {
        $rule = new IbanNumberRule();
        $iban = 'IR000000000000000000000000'; // Invalid MOD 97
        app()->detectEnvironment(fn() => 'production');
        $validator = validator(['iban' => $iban], ['iban' => [$rule]]);
        expect(fn() => $validator->validate())->toThrow(ValidationException::class);
    });
});
