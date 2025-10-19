<?php

declare(strict_types=1);

namespace App\Data\Admin\Teacher;

use App\Data\Admin\Settings\SocialMediaLinkData;
use App\Enums\User\GenderEnum;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class CreateTeacherData extends Data
{
    public function __construct(
        public string $first_name,
        public string $last_name,
        public string $bio,
        public float $rate,
        public string $email,
        public string $phone,
        public string $gender,
        public ?string $birth_date,
        public ?array $social_links,
        public int $user_id,
        public array $media,
    ) {
    }

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'first_name'              => ['required', 'string', 'max:255'],
            'last_name'               => ['required', 'string', 'max:255'],
            'bio'                     => ['required', 'string', 'max:1000'],
            'rate'                    => ['required', 'numeric', 'min:0', 'max:5', 'nullable'],
            'email'                   => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('teachers', 'email')->where(function (Builder $query) {
                    $teacher = request()->route()->parameter('teacher');
                    if ($teacher && $teacher->id) {
                        $query->whereNot('id', $teacher->id);
                    }

                    return $query;
                }),
            ],
            'phone'                   => [
                'required', 'string', 'max:20', 'nullable',
                Rule::unique('teachers', 'phone')->where(function (Builder $query) {
                    $teacher = request()->route()->parameter('teacher');
                    if ($teacher && $teacher->id) {
                        $query->whereNot('id', $teacher->id);
                    }

                    return $query;
                }),
            ],
            'gender'                  => ['required', Rule::enum(GenderEnum::class)],
            'birth_date'              => ['nullable', 'jdate:Y-m-d'],
            'social_links'            => ['nullable', 'array'],
            'social_links.*.platform' => ['required', 'string', 'max:50'],
            'social_links.*.link'     => ['required', 'url', 'max:255'],
            'user_id'                 => ['required', 'exists:users,id', 'integer'],
            'media'                   => ['present', 'array:avatar'],
            'media.avatar'            => ['nullable', 'integer', 'exists:media,id'],
        ];
    }

    public static function attributes(...$args): array
    {
        return [
            'first_name'   => __('validation.attributes.first_name'),
            'last_name'    => __('validation.attributes.last_name'),
            'bio'          => __('validation.attributes.teacher.bio'),
            'rate'         => __('validation.attributes.teacher.rate'),
            'email'        => __('validation.attributes.email'),
            'phone'        => __('validation.attributes.phone'),
            'gender'       => __('validation.attributes.gender'),
            'birth_date'   => __('validation.attributes.birth_date'),
            'social_links' => __('validation.attributes.social_links'),
            'user_id'      => __('validation.attributes.user_id'),
            'media'        => __('validation.attributes.media.self'),
            'media.avatar' => __('validation.attributes.media.profile'),
        ];
    }

    public static function messages(): array
    {
        return [
            'media.array' => 'فیلد رسانه باید آرایه باشد و فقط می‌تواند شامل کلید \'profile\' باشد و کلیدهای اضافی مجاز نیستند.',
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
            'first_name'   => [
                'description' => __('validation.attributes.first_name'),
                'example'     => 'John',
            ],
            'last_name'    => [
                'description' => __('validation.attributes.last_name'),
                'example'     => 'Doe',
            ],
            'bio'          => [
                'description' => __('validation.attributes.teacher.bio'),
                'example'     => 'An experienced teacher with a passion for education.',
            ],
            'rate'         => [
                'description' => __('validation.attributes.teacher.rate'),
                'example'     => 4.5,
            ],
            'email'        => [
                'description' => __('validation.attributes.email'),
                'example'     => 'teacher@example.com',
            ],
            'phone'        => [
                'description' => __('validation.attributes.phone'),
                'example'     => '+1234567890',
            ],
            'gender'       => [
                'description' => __('validation.attributes.gender'),
                'example'     => 'male',
            ],
            'birth_date'   => [
                'description' => __('validation.attributes.birth_date'),
                'example'     => '1990-01-01',
            ],
            'social_links' => [
                'description' => __('validation.attributes.social_links'),
                'example'     => [
                    'facebook' => 'https://www.facebook.com/teacher',
                    'twitter'  => 'https://www.twitter.com/teacher',
                ],
            ],
            'user_id'      => [
                'description' => __('validation.attributes.user_id').' ('.__('doc.teacher.user_id').')',
                'example'     => 1,
            ],
            'media'        => [
                'description' => __('validation.attributes.media.self'),
                'example'     => [
                    'profile' => 1,
                ],
            ],
            'media.avatar' => [
                'description' => __('validation.attributes.media.profile'),
                'example'     => 1,
            ],
        ];
    }
}
