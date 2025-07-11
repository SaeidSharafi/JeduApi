<?php

declare(strict_types=1);
if (! function_exists('get_model_label')) {
    /**
     * Get the class name from a fully qualified class name or object.
     *
     * @param  mixed  $class
     */
    function get_model_label(string|object $class): string
    {
        if (is_object($class) || class_exists($class)) {
            return __('messages.models.'.mb_strtolower(class_basename($class)));
        }

        return __('messages.models.'.mb_strtolower($class));
    }
}
if (! function_exists('randomNumber')) {
    function randomNumber($length = 20, $int = false): string|int
    {
        $numbers = '0123456789';

        $number = '';

        for ($i = 1; $i <= $length; $i++) {
            if ($i === 1) {
                $num = $numbers[rand(1, mb_strlen($numbers) - 1)];
            } else {
                $num = $numbers[rand(0, mb_strlen($numbers) - 1)];
            }

            $number .= $num;
        }

        if ($int) {
            return (int) $number;
        }

        return $number;
    }
}

if (!function_exists('getModelLabel')) {
    function getModelLabel(string $modelClass): string
    {
        if (!class_exists($modelClass)) {
            return __('messages.models.'.mb_strtolower($modelClass));
        }
        $modelName = class_basename($modelClass);

        return __('messages.models.'.mb_strtolower($modelName));
    }
}
if (!function_exists('httpStatusText')) {
    /**
     * Get the human readable text for an HTTP status code using localization.
     *
     * @param int $code
     * @return string
     */
    function httpStatusText(int $code): string
    {
        if ($code < 100 || $code > 599) {
            return (string) $code;
        }
        $key = 'messages.http_status.' . $code;
        $text = __($key);
        return $text === $key ? (string) $code : $text;
    }
}
