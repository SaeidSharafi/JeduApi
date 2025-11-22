<?php

declare(strict_types=1);

use App\Data\Transformer\CarbonFromJalaliString;
use App\Exceptions\InvalidJalaliDateException;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

/**
 * Helper function to simplify invoking the cast within tests.
 *
 * @param  mixed  $value  The value to be casted.
 * @param  string|null  $format  The date format passed to the cast's constructor.
 */
function castValue(mixed $value, ?string $format = null): ?Carbon
{
    $property           = (new ReflectionClass(DataProperty::class))->newInstanceWithoutConstructor();
    $reflectionProperty = new ReflectionProperty(DataProperty::class, 'name');
    $reflectionProperty->setValue($property, 'published_at');
    $caster      = new CarbonFromJalaliString($format);
    $mockContext = Mockery::mock(CreationContext::class);

    return $caster->cast($property, $value, [], $mockContext);
}

// --- Test Suite for CarbonFromJalaliString Cast ---

describe('Successful Casting Scenarios', function (): void {
    it('successfully casts a valid jalali datetime string with a specified format', function (): void {
        $jalaliString = '1403-05-06 15:30:00';
        $carbon       = castValue($jalaliString, 'Y-m-d H:i:s');

        expect($carbon)->toBeInstanceOf(Carbon::class)
            ->and($carbon->year)->toBe(2024)
            ->and($carbon->month)->toBe(7)
            ->and($carbon->day)->toBe(27)
            ->and($carbon->hour)->toBe(15)
            ->and($carbon->minute)->toBe(30);
    });

    it('successfully casts a valid jalali date string without a time component', function (): void {
        $jalaliString = '1403-05-06';
        $carbon       = castValue($jalaliString, 'Y-m-d');

        expect($carbon)->toBeInstanceOf(Carbon::class)
            ->and($carbon->year)->toBe(2024)
            ->and($carbon->month)->toBe(7)
            ->and($carbon->day)->toBe(27)
            ->and($carbon->isStartOfDay())->toBeTrue();
    });

    it('successfully casts a valid jalali string without a specified format', function (): void {
        $jalaliString = '1403/05/06'; // Uses Verta::parse() auto-detection
        $carbon       = castValue($jalaliString);

        expect($carbon)->toBeInstanceOf(Carbon::class)
            ->and($carbon->year)->toBe(2024)
            ->and($carbon->month)->toBe(7)
            ->and($carbon->day)->toBe(27);
    });

    it('returns the same instance when casting an existing carbon instance', function (): void {
        $originalCarbon = Carbon::create(2025, 1, 1, 12, 0, 0);
        $castedCarbon   = castValue($originalCarbon);

        expect($castedCarbon)->toBe($originalCarbon);
    });

    it('successfully casts an existing verta instance', function (): void {
        $verta  = new Verta('2024-7-27'); // Gregorian date to initialize Verta
        $carbon = castValue($verta);

        expect($carbon)->toBeInstanceOf(Carbon::class)
            ->and($carbon->year)->toBe(2024)
            ->and($carbon->month)->toBe(7)
            ->and($carbon->day)->toBe(27);
    });

    it('successfully casts a generic datetimeinterface instance', function (): void {
        $dateTime = new DateTimeImmutable('2025-02-15 10:00:00');
        $carbon   = castValue($dateTime);

        expect($carbon)->toBeInstanceOf(Carbon::class)
            ->and($carbon->year)->toBe(2025)
            ->and($carbon->month)->toBe(2)
            ->and($carbon->day)->toBe(15);
    });

    it('successfully casts a null value to null', function (): void {
        $carbon = castValue(null);
        expect($carbon)->toBeNull();
    });
});

describe('Failure and Exception Scenarios', function (): void {
    it('throws invalidjalalidateexception for a completely invalid date string', function (): void {
        castValue('not-a-date-at-all');
    })->throws(
        InvalidJalaliDateException::class,
        'The value for the [published_at] field is not a valid Jalali date format.'
    );

    it('throws invalidjalalidateexception for a logically invalid jalali date', function (): void {
        // Jalali month 13 does not exist
        castValue('1403-13-01', 'Y-m-d');
    })->throws(InvalidJalaliDateException::class);

    it('throws invalidjalalidateexception when the string does not match the provided format', function (): void {
        // Verta::parseFormat is strict. The format only expects a date, but gets a datetime.
        castValue('1403-05-06 12:00:00', 'Y-m-d');
    })->throws(InvalidJalaliDateException::class);

    it('throws invalidjalalidateexception for an invalid data type like an integer', function (): void {
        castValue(123456);
    })->throws(InvalidJalaliDateException::class);

    it('throws invalidjalalidateexception for an invalid data type like a boolean', function (): void {
        castValue(false);
    })->throws(InvalidJalaliDateException::class);
});

describe('Edge Case Scenarios', function (): void {
    it('handles jalali leap years correctly', function (): void {
        // 1399 was a Jalali leap year. Esfand (month 12) had 30 days.
        $jalaliLeapDayString = '1399-12-30'; // Corresponds to 2021-03-20
        $carbon              = castValue($jalaliLeapDayString, 'Y-m-d');

        expect($carbon)->toBeInstanceOf(Carbon::class)
            ->and($carbon->year)->toBe(2021)
            ->and($carbon->month)->toBe(3)
            ->and($carbon->day)->toBe(20);
    });

    it('throws an exception for a non-existent leap day in a non-leap year', function (): void {
        // 1400 was not a Jalali leap year. Esfand (month 12) only had 29 days.
        $jalaliNonLeapDayString = '1400-12-30';
        castValue($jalaliNonLeapDayString, 'Y-m-d');
    })->throws(InvalidJalaliDateException::class);

    it('handles dates with single-digit month and day when format allows it', function (): void {
        $jalaliString = '1403-1-5 08:05:01';
        // Use 'n' for month and 'j' for day without leading zeros
        $carbon = castValue($jalaliString, 'Y-n-j H:i:s');

        expect($carbon)->toBeInstanceOf(Carbon::class)
            ->and($carbon->year)->toBe(2024)
            ->and($carbon->month)->toBe(3)
            ->and($carbon->day)->toBe(24);
    });
});
