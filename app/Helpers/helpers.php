<?php
if (!function_exists('get_model_label')){
    /**
     * Get the class name from a fully qualified class name or object.
     *
     * @param mixed $class
     * @return string
     */
    function get_model_label(mixed $class): string
    {
        if (is_object($class) || class_exists($class)) {
            return __('messages.models.' . strtolower(class_basename($class)));
        }
        if (is_string($class)) {
            return __('messages.models.' . strtolower($class));
        }
        return '';
    }
}
if (!function_exists('randomNumber')) {
    function randomNumber($length = 20, $int = false)
    {
        $numbers = "0123456789";

        $number = '';

        for ($i = 1; $i <= $length; $i++) {
            if ($i == 1) {
                $num = $numbers[rand(1, strlen($numbers) - 1)];
            } else {
                $num = $numbers[rand(0, strlen($numbers) - 1)];
            }

            $number .= $num;
        }

        if ($int) {
            return (integer) $number;
        }

        return $number;
    }
}
