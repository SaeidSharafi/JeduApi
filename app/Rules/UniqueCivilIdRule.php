<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\CivilIdTypeEnum;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

final class UniqueCivilIdRule implements DataAwareRule, ValidationRule
{
    private array $data = [];

    public function __construct(private readonly ?int $userId = null) {}

    /**
     * Set the data under validation.
     * This method is automatically called by Laravel.
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $idTypeInput = $this->data['civil_id_type'] ?? null;

        if (is_null($idTypeInput) || empty($value)) {
            // This rule shouldn't run if the type is missing.
            // The 'required' rule on id_type should catch this, but this makes our rule robust.
            return;
        }

        $idTypeEnum = CivilIdTypeEnum::tryFrom($idTypeInput);
        if (is_null($idTypeEnum)) {
            // The id_type value was invalid (e.g., 'invalid_type').
            // The Enum rule on id_type will catch this, but again, we are being safe.
            return;
        }

        $userId = null;
        if ($this->userId) {
            $userId = $this->userId;
        }

        if (! $userId && $user = request()?->route()?->parameter('user')) {
            $userId = $user->id;
        }

        if (
            User::query()
                ->where('civil_id', $value)
                ->where('civil_id_type', $idTypeEnum->value)
                ->when($userId, fn ($query) => $query->whereNot('id', $userId))
                ->exists()
        ) {
            $fail(__('validation.unique', ['attribute' => __('validation.attributes.civil_id')]));
        }

    }
}
