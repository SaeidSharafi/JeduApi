<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\Content\PublicationStatusEnum;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

final class LearningPathStepsRule implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        if ($value === []) {
            if (data_get($this->data, 'status') !== PublicationStatusEnum::DRAFT->value) {
                $fail('A learning path must contain at least one step unless it is a draft.');
            }

            return;
        }

        $positions  = [];
        $references = [];
        $stepCount  = count($value);

        foreach ($value as $step) {
            if (! is_array($step)) {
                continue;
            }

            if (isset($step['position'])) {
                $positions[] = (int) $step['position'];
            }

            if (isset($step['productable_type'], $step['productable_id'])) {
                $reference = sprintf('%s:%s', $step['productable_type'], $step['productable_id']);
                if (isset($references[$reference])) {
                    $fail('A productable reference may only appear once in a learning path.');

                    return;
                }
                $references[$reference] = true;
            }
        }

        sort($positions);
        if ($positions !== range(1, $stepCount)) {
            $fail('Learning path step positions must be contiguous and start at 1.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }
}
