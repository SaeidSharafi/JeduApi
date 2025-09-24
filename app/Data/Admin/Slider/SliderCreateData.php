<?php

declare(strict_types=1);

namespace App\Data\Admin\Slider;

use App\Enums\PublicationStatusEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class SliderCreateData extends Data
{
    public function __construct(
        public string $title,
        public ?string $caption,
        public string $status,
        public int $image,
        public ?string $link,
        public int $order,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'title'   => ['required', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:255'],
            'status'  => ['required', 'string', Rule::enum(PublicationStatusEnum::class)],
            'image'   => ['required', 'integer', 'exists:media,id'],
            'link'    => ['nullable', 'string', 'max:255'],
            'order'   => ['required', 'integer', 'min:0'],
        ];
    }
}
