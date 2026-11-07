<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings;

use App\Data\Casts\SanitizeRichTextArray;
use App\Enums\Content\RichTextLevelEnum;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

final class FaqUpdateData extends Data
{
    public function __construct(
        #[WithCast(SanitizeRichTextArray::class, 'answer', RichTextLevelEnum::INLINE)]
        public array $items,
    ) {}

    public static function rules(): array
    {
        return [
            'items'            => ['present', 'array', 'list'],
            'items.*'          => ['required', 'array:question,answer'],
            'items.*.question' => ['required', 'string', 'max:255'],
            'items.*.answer'   => ['required', 'string'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'items'            => ['description' => 'Ordered FAQ items. An empty list clears the page.', 'example' => [['question' => 'چگونه ثبت‌نام کنم؟', 'answer' => 'دوره را به <strong>سبد خرید</strong> اضافه کنید.']]],
            'items.*.question' => ['description' => 'FAQ question.', 'example' => 'چگونه ثبت‌نام کنم؟'],
            'items.*.answer'   => ['description' => 'FAQ answer. Inline HTML is sanitized on save.', 'example' => 'دوره را به <strong>سبد خرید</strong> اضافه کنید.'],
        ];
    }
}
