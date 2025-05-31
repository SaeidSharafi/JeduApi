<?php

namespace App\Data\Admin;

use App\Rules\IranMobilePhoneRule;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class CreateAdminData extends Data
{
    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
        public string $password,
        public array $roles = [],
    ) {
    }

    public static function rules(ValidationContext $context): array
    {
        return [
            'name'    => ['required','string','max:255'],
            'email'   => ['required','email','max:255','unique:admins,email'],
            'phone'   => ['required',new IranMobilePhoneRule(),'unique:admins,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'roles'   => ['array'],
            'roles.*' => ['exists:roles,name'],
        ];
    }
}
