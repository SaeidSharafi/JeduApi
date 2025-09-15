<?php

declare(strict_types=1);

namespace App\Data\Admin\Blog\Category;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class BlogCategoryUpdateData extends Data
{
    public function __construct(
        public string $name,
        public ?string $slug = null,
        public ?string $description = null,
        public ?int $parent_id = null,
        public ?int $icon = null,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        $categoryId = request()->route('category');
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255',
                Rule::unique('blog_categories', 'slug')->ignore($categoryId)
            ],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:blog_categories,id'],
            'icon'        => ['nullable', 'integer:', 'exists:media,id'],
        ];
    }
}
