<?php

declare(strict_types=1);

namespace App\Data\Admin\User;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class UpdateUserAvatarData extends Data
{
    public function __construct(
        public UploadedFile $file,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        $maxFileSize = config('mediable.max_size') / 1024;

        return [
            'file' => ['required', 'file', 'max:'.$maxFileSize],
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
            'file' => [
                'description' => 'The avatar image to upload.',
                'example'     => null,
            ],
        ];
    }
}
