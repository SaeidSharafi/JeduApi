<?php

declare(strict_types=1);

describe('get_model_label', function (): void {
    it('returns model label for a class string', function (): void {
        $label = get_model_label(User::class);
        expect($label)->toBe(__('messages.models.user'));
    });

    it('returns model label for an object', function (): void {
        $user  = new App\Models\User();
        $label = get_model_label($user);
        expect($label)->toBe(__('messages.models.user'));
    });

    it('returns model label for a simple string if class does not exist', function (): void {
        $label = get_model_label('SomeModel');
        expect($label)->toBe(__('messages.models.somemodel'));
    });

    it('returns model label for a class string that does not exist but follows convention', function (): void {
        $label = get_model_label('NonExistentModel');
        expect($label)->toBe(__('messages.models.nonexistentmodel'));
    });
});

describe('randomNumber', function (): void {
    it('generates a random string of default length', function (): void {
        $number = randomNumber();
        expect($number)->toBeString()
            ->and(mb_strlen($number))->toBe(20)
            ->and($number[0])->not->toBe('0');
    });

    it('generates a random string of specified length', function (): void {
        $number = randomNumber(10);
        expect($number)->toBeString()
            ->and(mb_strlen($number))->toBe(10)
            ->and($number[0])->not->toBe('0');
    });

    it('generates a random integer of default length', function (): void {
        $number = randomNumber(20, true);
        expect($number)->toBeInt()
            ->and(mb_strlen((string) $number))->toBeLessThanOrEqual(19);
    });

    it('generates a random integer of specified length', function (): void {
        $number = randomNumber(5, true);
        expect($number)->toBeInt()
            ->and(mb_strlen((string) $number))->toBe(5)
            ->and(((string) $number)[0])->not->toBe('0');
    });

    it('generates a random integer of length 1', function (): void {
        $number = randomNumber(1, true);
        expect($number)->toBeInt()
            ->and($number)->toBeGreaterThanOrEqual(1)
            ->and($number)->toBeLessThanOrEqual(9);
    });

    it('generates a random string of length 1', function (): void {
        $number = randomNumber(1);
        expect($number)->toBeString()
            ->and(mb_strlen($number))->toBe(1)
            ->and($number)->toMatch('/^[1-9]$/');
    });
});

describe('getModelLabel', function (): void {
    it('returns model label for a class string', function (): void {
        $label = getModelLabel(App\Models\User::class);

        expect($label)->toBe(__('messages.models.user'));
    });

    it('returns label for invalid model', function (): void {
        $label = getModelLabel('invalid_model');

        expect($label)->toBe(__('messages.models.invalid_model'));
    });
});

describe('httpStatusText', function (): void {
    it('returns localized text for a valid HTTP status code', function (): void {
        $text = httpStatusText(200);
        expect($text)->toBe(__('messages.http_status.200'));
    });

    it('returns localized text for a 404 status code', function (): void {
        $text = httpStatusText(404);
        expect($text)->toBe(__('messages.http_status.404'));
    });

    it('returns localized text for an unknown status code', function (): void {
        $text = httpStatusText(999);
        expect($text)->toBe('999');
    });
});

describe('formatFileSize', function (): void {
    it('returns null for null input', function (): void {
        expect(formatFileSize(null))->toBeNull();
    });

    it('returns plain bytes for values below one kilobyte', function (): void {
        expect(formatFileSize(0))->toBe('0 B')
            ->and(formatFileSize(999))->toBe('999 B');
    });

    it('formats the exact kilobyte boundary', function (): void {
        // 1000 / 1000 = 1.0 -> number_format '1.0' -> rtrim '0' -> '1.' -> rtrim '.' -> '1'
        expect(formatFileSize(1000))->toBe('1 KB');
    });

    it('rounds values just above the kilobyte boundary to one decimal', function (): void {
        // 1001 / 1000 = 1.001 -> number_format rounds to '1.0' -> '1'
        expect(formatFileSize(1001))->toBe('1 KB')
            ->and(formatFileSize(1500))->toBe('1.5 KB');
    });

    it('rounds fractional kilobytes below one', function (): void {
        // 999999 / 1000 = 999.999 -> >= 1e3 branch: 999.999 -> number_format '1000.0' -> '1000'
        expect(formatFileSize(999_999))->toBe('1000 KB');
    });

    it('formats megabytes', function (): void {
        expect(formatFileSize(1_000_000))->toBe('1 MB')
            ->and(formatFileSize(1_234_567))->toBe('1.2 MB')
            ->and(formatFileSize(1_500_000))->toBe('1.5 MB');
    });

    it('rounds a value just below the gigabyte boundary inside the megabyte branch', function (): void {
        // 999999999 < 1e9, so MB branch: 999.999999 -> number_format '1000.0' -> '1000'
        expect(formatFileSize(999_999_999))->toBe('1000 MB');
    });

    it('formats gigabytes', function (): void {
        expect(formatFileSize(1_000_000_000))->toBe('1 GB')
            ->and(formatFileSize(2_500_000_000))->toBe('2.5 GB')
            ->and(formatFileSize(1_234_567_890))->toBe('1.2 GB');
    });
});
