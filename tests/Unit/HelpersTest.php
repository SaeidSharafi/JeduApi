<?php

declare(strict_types=1);

describe('get_model_label', function () {
    it('returns model label for a class string', function () {
        $label = get_model_label(User::class);
        expect($label)->toBe(__('messages.models.user'));
    });

    it('returns model label for an object', function () {
        $user  = new App\Models\User();
        $label = get_model_label($user);
        expect($label)->toBe(__('messages.models.user'));
    });

    it('returns model label for a simple string if class does not exist', function () {
        $label = get_model_label('SomeModel');
        expect($label)->toBe(__('messages.models.somemodel'));
    });

    it('returns model label for a class string that does not exist but follows convention', function () {
        $label = get_model_label('NonExistentModel');
        expect($label)->toBe(__('messages.models.nonexistentmodel'));
    });
});

describe('randomNumber', function () {
    it('generates a random string of default length', function () {
        $number = randomNumber();
        expect($number)->toBeString()
            ->and(mb_strlen($number))->toBe(20)
            ->and($number[0])->not->toBe('0');
    });

    it('generates a random string of specified length', function () {
        $number = randomNumber(10);
        expect($number)->toBeString()
            ->and(mb_strlen($number))->toBe(10)
            ->and($number[0])->not->toBe('0');
    });

    it('generates a random integer of default length', function () {
        $number = randomNumber(20, true);
        expect($number)->toBeInt()
            ->and(mb_strlen((string) $number))->toBeLessThanOrEqual(19);
    });

    it('generates a random integer of specified length', function () {
        $number = randomNumber(5, true);
        expect($number)->toBeInt()
            ->and(mb_strlen((string) $number))->toBe(5)
            ->and(((string) $number)[0])->not->toBe('0');
    });

    it('generates a random integer of length 1', function () {
        $number = randomNumber(1, true);
        expect($number)->toBeInt()
            ->and($number)->toBeGreaterThanOrEqual(1)
            ->and($number)->toBeLessThanOrEqual(9);
    });

    it('generates a random string of length 1', function () {
        $number = randomNumber(1);
        expect($number)->toBeString()
            ->and(mb_strlen($number))->toBe(1)
            ->and($number)->toMatch('/^[1-9]$/');
    });
});

describe('getModelLabel', function (){
    it('returns model label for a class string', function () {
        $label = getModelLabel(\App\Models\User::class);

        expect($label)->toBe(__('messages.models.user'));
    });

    it('returns label for invalid model', function () {
        $label = getModelLabel('invalid_model');

        expect($label)->toBe(__('messages.models.invalid_model'));
    });
});

describe("httpStatusText", function (){
    it('returns localized text for a valid HTTP status code', function () {
        $text = httpStatusText(200);
        expect($text)->toBe(__('messages.http_status.200'));
    });

    it('returns localized text for a 404 status code', function () {
        $text = httpStatusText(404);
        expect($text)->toBe(__('messages.http_status.404'));
    });

    it('returns localized text for an unknown status code', function () {
        $text = httpStatusText(999);
        expect($text)->toBe('999');
    });
});
