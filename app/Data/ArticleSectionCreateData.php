<?php

declare(strict_types=1);

namespace App\Data;

use App\Data\Casts\SanitizeRichText;
use App\Enums\Content\RichTextLevelEnum;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

final class ArticleSectionCreateData extends Data
{
    public function __construct(
        public string $title,
        #[WithCast(SanitizeRichText::class, RichTextLevelEnum::COMPACT)]
        public readonly string $content,
        public ?int $icon = null,
        public readonly ?string $subtitle = null,
    ) {}
}
