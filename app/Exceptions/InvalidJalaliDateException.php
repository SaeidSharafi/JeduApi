<?php

namespace App\Exceptions;

use Exception;

class InvalidJalaliDateException extends Exception
{
    /**
     * Create a new exception instance.
     *
     * @param string $property The name of the property that failed casting.
     * @param mixed $value The value that failed to cast.
     */
    public function __construct(
        public string $property,
        public mixed $value
    ) {
        $message = "The value for the [{$property}] field is not a valid Jalali date format.";
        parent::__construct($message);
    }
}
