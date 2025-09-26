<?php

declare(strict_types=1);

namespace App\Data\Admin\Slider;

use App\Enums\Content\PublicationStatusEnum;
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

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'title' => [
                'description' => 'Title of the slider.',
                'example'     => 'Welcome Banner',
            ],
            'caption' => [
                'description' => 'Caption for the slider.',
                'example'     => 'Start your learning journey!',
            ],
            'status' => [
                'description' => 'Publication status value.',
                'example'     => PublicationStatusEnum::PUBLISHED->value,
            ],
            'image' => [
                'description' => 'Media ID for the slider image.',
                'example'     => 201,
            ],
            'link' => [
                'description' => 'Optional link for the slider.',
                'example'     => 'https://jedu.ir/slider',
            ],
            'order' => [
                'description' => 'Display order for the slider.',
                'example'     => 1,
            ],
        ];
    }
}
