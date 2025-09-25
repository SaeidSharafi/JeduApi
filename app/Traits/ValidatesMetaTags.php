<?php

declare(strict_types=1);

namespace App\Traits;

trait ValidatesMetaTags
{
    /**
     * Get the validation rules for meta tags.
     */
    protected static function metaTagValidationRules(): array
    {
        return [
            'meta_title'       => ['required', 'string', 'max:70'],
            'meta_description' => ['required', 'string', 'min:70', 'max:160'],
            'meta_keywords'    => ['nullable', 'string', 'max:255'],
        ];
    }

    protected static function metaTagBodyParameters(): array
    {
        return [
            'meta_title' => [
                'description' => 'The meta title for SEO purposes.',
                'example'     => 'Best Products Online',
            ],
            'meta_description' => [
                'description' => 'The meta description for SEO purposes.',
                'example'     => 'Find the best products online at unbeatable prices.',
            ],
            'meta_keywords' => [
                'description' => 'The meta keywords for SEO purposes.',
                'example'     => 'products, online shopping, best prices',
            ],
        ];
    }
}
