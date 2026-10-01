<?php

declare(strict_types=1);

namespace App\Data\Admin\LearningPath;

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\LearningPath;
use App\Rules\LearningPathProductableExistRule;
use App\Rules\LearningPathStepsRule;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

final class LearningPathUpdateData extends Data
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
        $learningPath = request()->route('learningPath') ?? request()->route('learning_path');
        $id           = $learningPath instanceof LearningPath ? $learningPath->getKey() : null;
        $slugRule     = Rule::unique('learning_paths', 'slug');
        if ($id !== null) {
            $slugRule = $slugRule->ignore($id);
        }

        return [
            'title'                    => ['required', 'string', 'max:255'],
            'slug'                     => ['required', 'alpha_dash', 'max:255', $slugRule],
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
            'media'                  => ['required', 'array:cover,gallery'],
            'media.gallery'          => ['required', 'array'],
            'media.cover'            => ['required', 'array'],
            'media.cover.*'          => ['required', 'integer', 'exists:media,id'],
            'media.gallery.*'        => ['required', 'integer', 'exists:media,id'],
        ];
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return LearningPathCreateData::bodyParameterDefinitions();
    }
}
