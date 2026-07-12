<?php

declare(strict_types=1);

use App\Exceptions\InvalidJalaliDateException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

it('throws ValidationException from render()', function (): void {
    $exception = new InvalidJalaliDateException(
        property: 'birth_date',
        value: '1402/13/01',
    );

    $request = Request::create('/', 'GET');

    try {
        $exception->render($request);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->getMessage())->toContain('value for the [birth_date] field is not a valid Jalali date format.');
    }
});
