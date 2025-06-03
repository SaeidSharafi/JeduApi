<?php

declare(strict_types=1);

use App\Data\Transformer\CarbonFromJalaliString;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

it('casts a Verta instance to Carbon', function () {
    $caster    = new CarbonFromJalaliString();
    $vertaDate = Verta::create(2023, 3, 21, 10, 30, 0); // Corresponds to 1402-01-01 10:30:00

    $mockProperty = Mockery::mock(DataProperty::class);
    $mockContext  = Mockery::mock(CreationContext::class);

    $carbonDate = $caster->cast($mockProperty, $vertaDate, [], $mockContext);

    expect($carbonDate)->toBeInstanceOf(Carbon::class)
        ->and($carbonDate->year)->toBe(2023)
        ->and($carbonDate->month)->toBe(3)
        ->and($carbonDate->day)->toBe(21)
        ->and($carbonDate->hour)->toBe(10)
        ->and($carbonDate->minute)->toBe(30)
        ->and($carbonDate->second)->toBe(0);
});

it('casts a DateTimeInterface instance to Carbon', function () {
    $caster        = new CarbonFromJalaliString();
    $gregorianDate = Carbon::create(2023, 3, 21, 12, 0, 0);

    $mockProperty = Mockery::mock(DataProperty::class);
    $mockContext  = Mockery::mock(CreationContext::class);

    $carbonDate = $caster->cast($mockProperty, $gregorianDate, [], $mockContext);

    expect($carbonDate)->toBeInstanceOf(Carbon::class)
        ->and($carbonDate->equalTo($gregorianDate))->toBeTrue();
});

it('casts a valid Jalali date string to Carbon', function () {
    $caster           = new CarbonFromJalaliString();
    $jalaliDateString = '1402-01-01 15:45:30'; // Corresponds to 2023-03-21 15:45:30

    $mockProperty = Mockery::mock(DataProperty::class);
    $mockContext  = Mockery::mock(CreationContext::class);

    $carbonDate = $caster->cast($mockProperty, $jalaliDateString, [], $mockContext);

    expect($carbonDate)->toBeInstanceOf(Carbon::class)
        ->and($carbonDate->year)->toBe(2023)
        ->and($carbonDate->month)->toBe(3)
        ->and($carbonDate->day)->toBe(21)
        ->and($carbonDate->hour)->toBe(15)
        ->and($carbonDate->minute)->toBe(45)
        ->and($carbonDate->second)->toBe(30);
});

it('throws an exception for an invalid date string format', function () {
    $caster            = new CarbonFromJalaliString();
    $invalidDateString = '1402/01/01 10:00'; // Invalid format

    $mockProperty = Mockery::mock(DataProperty::class);
    $mockContext  = Mockery::mock(CreationContext::class);

    $caster->cast($mockProperty, $invalidDateString, [], $mockContext);
})->throws(InvalidArgumentException::class, 'Cannot cast value to Carbon from Jalali string: string');

it('throws an exception for an unsupported type', function ($invalidValue) {
    $caster = new CarbonFromJalaliString();

    $mockProperty    = Mockery::mock(DataProperty::class);
    $mockContext     = Mockery::mock(CreationContext::class);
    $expectedMessage = 'Cannot cast value to Carbon from Jalali string: '.gettype($invalidValue);

    expect(fn () => $caster->cast($mockProperty, $invalidValue, [], $mockContext))
        ->toThrow(InvalidArgumentException::class, $expectedMessage);
})->with([
    123,
    123.45,
    true,
    new stdClass(),
]);
