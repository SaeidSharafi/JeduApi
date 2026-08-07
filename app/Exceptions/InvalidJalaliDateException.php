<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class InvalidJalaliDateException extends Exception
{
    /**
     * Create a new exception instance.
     *
     * @param  string  $property  The name of the property that failed casting.
     * @param  mixed  $value  The value that failed to cast.
     */
    public function __construct(
        public string $property,
        public mixed $value
    ) {
        $message = __('messages.validation.invalid_jalali_date', ['prop' => $property]);
        parent::__construct($message);
    }

    /**
     * Convert this exception to a ValidationException for a 422 response.
     */
    public function render(Request $request): never
    {
        throw ValidationException::withMessages([
            $this->property => [$this->getMessage()],
        ]);
    }
}
