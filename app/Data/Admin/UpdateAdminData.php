<?php

namespace App\Data\Admin;

use App\Rules\IranMobilePhoneRule;
use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class UpdateAdminData extends Data
{
    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
        public ?string $password,
        public array $roles = [],
    ) {
    }

    public static function rules(ValidationContext $context): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => [
                'required', 'email', 'max:255',
                Rule::unique('admins', 'email')->where(function (Builder $query) {
                    $admin = request()->route()->parameter('admin');
                    if ($admin && $admin->id) {
                        $query->whereNot('id', $admin->id);
                    }

                    return $query;
                }),
            ],
            'phone'    => ['required', new IranMobilePhoneRule(),
                Rule::unique('admins', 'phone')->where(function (Builder $query) {
                    $admin = request()->route()->parameter('admin');
                    if ($admin && $admin->id) {
                        $query->whereNot('id', $admin->id);
                    }

                    return $query;
                }),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'roles'    => ['array'],
            'roles.*'  => ['exists:roles,name'],
        ];
    }
}
