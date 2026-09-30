<?php

declare(strict_types=1);

namespace App\Data\Admin\LearningPath;

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\MediaTagEnum;
use App\Enums\Product\ProductableEnum;
use App\Rules\LearningPathProductableExistRule;
use App\Rules\LearningPathStepsRule;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

final class LearningPathCreateData extends Data
{
    public function __construct(
        public string $title,
        public string $slug,
        public string $description,
        public string $introduction_title,
        public string $introduction_description,
        public string $conclusion_title,
        public string $conclusion_description,
        public string $status = 'draft',
        public ?string $meta_title = null,
        public ?string $meta_description = null,
        public ?string $meta_keywords = null,
        public int $display_order = 0,
        public array $steps = [],
        public array $media = [],
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'title'                    => ['required', 'string', 'max:255'],
            'slug'                     => ['required', 'alpha_dash', 'max:255', 'unique:learning_paths,slug'],
            'description'              => ['required', 'string'],
            'introduction_title'       => ['required', 'string', 'max:255'],
            'introduction_description' => ['required', 'string'],
            'conclusion_title'         => ['required', 'string', 'max:255'],
            'conclusion_description'   => ['required', 'string'],
            'status'                   => [
                'required',
                'string',
                Rule::in([
                    PublicationStatusEnum::DRAFT->value,
                    PublicationStatusEnum::PUBLISHED->value,
                    PublicationStatusEnum::ARCHIVED->value,
                ]),
            ],
            'meta_title'               => ['nullable', 'string', 'max:70'],
            'meta_description'         => ['nullable', 'string', 'max:160'],
            'meta_keywords'            => ['nullable', 'string', 'max:255'],
            'display_order'            => ['required', 'integer', 'min:0'],
            'steps'                    => ['present', 'array', new LearningPathStepsRule()],
            'steps.*'                  => ['required', 'array'],
            'steps.*.position'         => ['required', 'integer', 'min:1'],
            'steps.*.productable_type' => [
                'required',
                'string',
                Rule::in([
                    ProductableEnum::COURSE->value,
                    ProductableEnum::SEMINAR->value,
                    ProductableEnum::DIGITAL_ASSET->value,
                ]),
            ],
            'steps.*.productable_id' => ['required', 'integer', new LearningPathProductableExistRule()],
            'steps.*.title'          => ['required', 'string', 'max:255'],
            'steps.*.description'    => ['required', 'string'],
            ...self::mediaValidationRules(),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function bodyParameterDefinitions(): array
    {
        return [
            'title' => [
                'description' => 'The learning path title.',
                'example'     => 'Backend Engineering Path',
            ],
            'slug' => [
                'description' => 'A unique editable URL-safe slug.',
                'example'     => 'backend-engineering-path',
            ],
            'description' => [
                'description' => 'The learning path description.',
                'example'     => 'A guided path for backend engineering.',
            ],
            'introduction_title' => [
                'description' => 'Required title shown for the path introduction.',
                'example'     => 'Start here',
            ],
            'introduction_description' => [
                'description' => 'Required description shown for the path introduction.',
                'example'     => 'Begin with the foundations.',
            ],
            'conclusion_title' => [
                'description' => 'Required title shown for the path conclusion.',
                'example'     => 'Keep building',
            ],
            'conclusion_description' => [
                'description' => 'Required description shown for the path conclusion.',
                'example'     => 'Apply what you learned.',
            ],
            'status' => [
                'description' => 'Learning path lifecycle status.',
                'example'     => PublicationStatusEnum::DRAFT->value,
            ],
            'meta_title' => [
                'description' => 'Optional SEO meta title.',
                'example'     => 'Backend Engineering Learning Path',
            ],
            'meta_description' => [
                'description' => 'Optional SEO meta description.',
                'example'     => 'A practical backend engineering learning path.',
            ],
            'meta_keywords' => [
                'description' => 'Optional comma-separated SEO keywords.',
                'example'     => 'backend, laravel, engineering',
            ],
            'display_order' => [
                'description' => 'Explicit public display order.',
                'example'     => 2,
            ],
            'steps' => [
                'description' => 'Ordered path steps. An empty array is valid for drafts.',
                'example'     => [],
            ],
            'steps.*.position' => [
                'description' => 'Contiguous step position starting at 1.',
                'example'     => 1,
            ],
            'steps.*.productable_type' => [
                'description' => 'Referenced productable type: course, seminar, or digital_asset.',
                'example'     => ProductableEnum::COURSE->value,
            ],
            'steps.*.productable_id' => [
                'description' => 'ID of the existing referenced productable.',
                'example'     => 42,
            ],
            'steps.*.title' => [
                'description' => 'Editorial step title.',
                'example'     => 'Learn the course',
            ],
            'steps.*.description' => [
                'description' => 'Editorial step description.',
                'example'     => 'Build the required foundation.',
            ],
            'media' => [
                'description' => 'Optional media IDs grouped by existing media tag.',
                'example'     => ['cover' => [1], 'gallery' => [2, 3]],
            ],
            'media.*.*' => [
                'description' => 'An existing media ID.',
                'example'     => 1,
            ],
        ];
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return self::bodyParameterDefinitions();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private static function mediaValidationRules(): array
    {
        $tags = implode(',', array_map(
            static fn (MediaTagEnum $tag): string => $tag->value,
            MediaTagEnum::cases(),
        ));

        return [
            'media'     => ['sometimes', 'array:'.$tags],
            'media.*'   => ['sometimes', 'array'],
            'media.*.*' => ['integer', 'exists:media,id'],
        ];
    }
}
