<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings;

use App\Data\Admin\MediaData;
use Spatie\LaravelData\Data;

final class CollaborationPageCreateData extends Data
{
    public function __construct(
        public string $title,
        public string $content,
        public ?int $image,

    ) {
    }

    public static function rules(): array
    {
        return [
            'title'   => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'image'   => ['nullable', 'integer', 'exists:media,id'],
        ];
    }

    /**
     * @codeCoverageIgnore
     */

    public static function bodyParameters(): array
    {
        return [
            'title'   => [
                'description' => 'The title of the collaboration page.',
                'example'     => 'فرصت همکاری',
            ],
            'content' => [
                'description' => 'The HTML content of the collaboration page.',
                'example'     => '<h3>فرصت همکاری با موسسه آموزشی جهاد دانشگاهی استان قزوین</h3>'
            ],
            'image'   => [
                'description' => 'The ID of the image (media) associated with the collaboration page.',
                'example'     => 5,
            ],
        ];
    }
}
