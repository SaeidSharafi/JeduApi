<?php

namespace App\Data\Teacher;

use App\Enums\GenderEnum;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class CreateTeacherData extends Data
{
    public function __construct(
        public string $first_name,
        public string $last_name,
        public string $bio,
        public float $rate = 0.0,
        public string $email,
        public string $phone,
        public string $gender,
        public ?string $birth_date = null,
        public ?array $social_links = null,
        public int $user_id,
        public array $media,
    ) {
    }

    public static function rules(ValidationContext $context): array
    {
        return [
            'first_name'     => ['required', 'string', 'max:255'],
            'last_name'      => ['required', 'string', 'max:255'],
            'bio'            => ['required', 'string', 'max:1000'],
            'rate'           => ['required', 'numeric', 'min:0', 'max:5', 'nullable'],
            'email'          => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('teachers', 'email')->where(function (Builder $query) {
                    $teacher = request()->route()->parameter('teacher');
                    if ($teacher && $teacher->id) {
                        $query->whereNot('id', $teacher->id);
                    }
                    return $query;
                })
            ],
            'phone'          => [
                'required', 'string', 'max:20', 'nullable',
                Rule::unique('teachers', 'phone')->where(function (Builder $query) {
                    $teacher = request()->route()->parameter('teacher');
                    if ($teacher && $teacher->id) {
                        $query->whereNot('id', $teacher->id);
                    }
                    return $query;
                })
            ],
            'gender'         => ['required', Rule::enum(GenderEnum::class)],
            'birth_date'     => ['nullable', 'date_format:Y-m-d'],
            'social_links'   => ['nullable', 'array'],
            'social_links.*' => ['nullable', 'url'],
            'user_id'        => ['required', 'exists:users,id', 'integer'],
            'media'          => ['present', 'array:profile'],
            'media.profile'   => ['nullable', 'integer', 'exists:media,id'],
        ];
    }

    public static function attributes(...$args): array
    {
        return [
            'first_name' => __('validation.attributes.first_name'),
            'last_name'  => __('validation.attributes.last_name'),
            'bio'        => __('validation.attributes.teacher.bio'),
            'rate'       => __('validation.attributes.teacher.rate'),
            'email'      => __('validation.attributes.email'),
            'phone'      => __('validation.attributes.phone'),
            'gender'     => __('validation.attributes.gender'),
            'birth_date' => __('validation.attributes.birth_date'),
            'social_links' => __('validation.attributes.social_links'),
            'user_id'    => __('validation.attributes.user_id'),
            'media'      => __('validation.attributes.media.self'),
            'media.profile' => __('validation.attributes.media.profile'),
        ];
    }

    public static function messages(): array
    {
        return [
            'media.array' => 'فیلد رسانه باید آرایه باشد و فقط می‌تواند شامل کلید \'profile\' باشد و کلیدهای اضافی مجاز نیستند.',
        ];
    }
}
