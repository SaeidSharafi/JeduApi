<?php

namespace App\Traits;

trait ValidatesMetaTags
{
    /**
     * Get the validation rules for meta tags.
     *
     * @return array
     */
    protected static function metaTagValidationRules(): array
    {
        return [
            'meta_title'       => ['required', 'string', 'max:70'],
            'meta_description' => ['required', 'string', 'min:70', 'max:160'],
            'meta_keywords'    => ['nullable', 'string', 'max:255'],
        ];
    }
}
