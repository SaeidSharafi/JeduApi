<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings;

use App\Data\Casts\SanitizeFaqCategories;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

final class FaqUpdateData extends Data
{
    public function __construct(
        #[WithCast(SanitizeFaqCategories::class)]
        public array $categories,
    ) {}

    public static function rules(): array
    {
        return [
            'categories'                    => ['present', 'array', 'list'],
            'categories.*'                  => ['required', 'array:title,items'],
            'categories.*.title'            => ['required', 'string', 'max:255'],
            'categories.*.items'            => ['present', 'array', 'list'],
            'categories.*.items.*'          => ['required', 'array:question,answer'],
            'categories.*.items.*.question' => ['required', 'string', 'max:255'],
            'categories.*.items.*.answer'   => ['required', 'string'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'categories'                    => ['description' => 'Ordered FAQ categories. An empty list clears the page.', 'example' => [['title' => 'ثبت‌نام', 'items' => [['question' => 'چگونه ثبت‌نام کنم؟', 'answer' => 'دوره را به <strong>سبد خرید</strong> اضافه کنید.']]]]],
            'categories.*.title'            => ['description' => 'Category title.', 'example' => 'ثبت‌نام'],
            'categories.*.items'            => ['description' => 'Ordered questions in the category.', 'example' => [['question' => 'چگونه ثبت‌نام کنم؟', 'answer' => 'دوره را به سبد خرید اضافه کنید.']]],
            'categories.*.items.*.question' => ['description' => 'FAQ question.', 'example' => 'چگونه ثبت‌نام کنم؟'],
            'categories.*.items.*.answer'   => ['description' => 'FAQ answer. Inline HTML is sanitized on save.', 'example' => 'دوره را به <strong>سبد خرید</strong> اضافه کنید.'],
        ];
    }
}
