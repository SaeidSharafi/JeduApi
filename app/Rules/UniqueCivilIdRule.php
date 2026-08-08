<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\User\CivilIdTypeEnum;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

final class UniqueCivilIdRule implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(private readonly ?int $userId = null) {}

    /**
     * Set the data under validation.
     * This method is automatically called by Laravel.
     */
    /**
     * @param  array<string, mixed>  $data
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

        $routeUser = request()->route()?->parameter('user');

        if ($userId === null && $routeUser instanceof User) {
            $userId = (int) $routeUser->getKey();
        } elseif ($userId === null && is_string($routeUser) && ctype_digit($routeUser)) {
            $userId = (int) $routeUser;
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
