<?php

declare(strict_types=1);

namespace App\Data\Shop\Webhooks;

use Illuminate\Contracts\Validation\Validator;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class PorslineSurveySubmissionData extends Data
{
    /**
     * @param  array<int, array{name: string, value: mixed}>  $variables
     * @param  array<int, array<string, mixed>>|null  $questions
     * @param  array<int, array<string, mixed>>|null  $computational_variables
     * @param  array<string, mixed>|null  $respondent_data
     */
    public function __construct(
        public array $variables,
        public ?array $questions = null,
        public ?array $computational_variables = null,
        public ?array $respondent_data = null,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'variables'               => ['required', 'array', 'min:1'],
            'variables.*'             => ['required', 'array'],
            'variables.*.name'        => ['required', 'string'],
            'variables.*.value'       => ['present'],
            'questions'               => ['nullable', 'array'],
            'computational_variables' => ['nullable', 'array'],
            'respondent_data'         => ['nullable', 'array'],
        ];
    }

    public static function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $variables = $validator->getData()['variables'] ?? null;

            if (! is_array($variables) || ! array_is_list($variables)) {
                $validator->errors()->add('variables', __('messages.enrollments.survey_token_invalid'));

                return;
            }

            $tokens = collect($variables)
                ->filter(fn (mixed $variable): bool => is_array($variable)
                    && ($variable['name'] ?? null) === 'enrollment_token')
                ->values();

            if ($tokens->count() !== 1) {
                $validator->errors()->add('variables', __('messages.enrollments.survey_token_invalid'));

                return;
            }

            $token = $tokens->first()['value'] ?? null;

            if (! is_string($token) || preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
                $validator->errors()->add('variables', __('messages.enrollments.survey_token_invalid'));
            }
        });
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public static function bodyParameters(): array
    {
        return [
            'variables' => [
                'description' => 'Porsline custom variables submitted as name/value entries.',
                'example'     => [['name' => 'enrollment_token', 'value' => str_repeat('a', 64)]],
            ],
            'variables.*.name' => [
                'description' => 'The custom variable name. Exactly one entry must be named enrollment_token.',
                'example'     => 'enrollment_token',
            ],
            'variables.*.value' => [
                'description' => 'The custom variable value. enrollment_token must be a 64-character lowercase hexadecimal token.',
                'example'     => str_repeat('a', 64),
            ],
            'questions' => [
                'description' => 'Optional Porsline question and answer metadata. It is accepted but not stored.',
                'example'     => [],
            ],
            'computational_variables' => [
                'description' => 'Optional Porsline computed-variable metadata. It is accepted but not stored.',
                'example'     => [],
            ],
            'respondent_data' => [
                'description' => 'Optional Porsline respondent metadata. It is accepted but not stored.',
                'example'     => [],
            ],
        ];
    }
}
