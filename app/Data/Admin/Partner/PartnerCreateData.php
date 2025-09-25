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
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'title'     => ['required', 'string', 'max:255'],
            'caption'   => ['nullable', 'string', 'max:255'],
            'image'     => ['required', 'integer', 'exists:media,id'],
            'url'       => ['nullable', 'string', 'max:255'],
            'show_in'   => ['required', 'string', 'in:home,course'],
            'order'     => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'title' => [
                'description' => 'Title of the partner.',
                'example'     => 'Jedu Academy',
            ],
            'caption' => [
                'description' => 'Short caption for the partner.',
                'example'     => 'Leading online education provider.',
            ],
            'image' => [
                'description' => 'Media ID for the partner logo.',
                'example'     => 101,
            ],
            'url' => [
                'description' => 'URL for the partner.',
                'example'     => 'https://partner.com',
            ],
            'show_in' => [
                'description' => 'Where to show the partner: home or course.',
                'example'     => 'home',
            ],
            'order' => [
                'description' => 'Display order for the partner.',
                'example'     => 1,
            ],
            'is_active' => [
                'description' => 'Whether the partner is active.',
                'example'     => true,
            ],
        ];
    }
}
