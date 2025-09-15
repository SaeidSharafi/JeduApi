<?php

declare(strict_types=1);

namespace App\Data\Admin\Partner;

use App\Enums\PartnerShowInEnum;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class PartnerCreateData extends Data
{
    public function __construct(
        public string $title,
        public ?string $caption,
        public int $image,
        public ?string $url,
        public PartnerShowInEnum $show_in,
        public int $order,
        public bool $is_active = false,
    ) {
    }

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'title'     => ['required', 'string', 'max:255'],
            'caption'   => ['nullable', 'string', 'max:255'],
            'image'     => ['required', 'integer', 'exists:media,id'],
            'url'       => ['nullable', 'string', 'max:255'],
            'show_in'   => ['required', 'string', 'in:home,course'],
            'order'     => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean']
        ];
    }
}
