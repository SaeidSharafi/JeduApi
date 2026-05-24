<?php

declare(strict_types=1);

namespace App\Http\Requests\Students;

use App\Enums\Student\EducationLevelEnum;
use App\Enums\Student\EducationStatusEnum;
use App\Enums\Student\RelationTypeEnum;
use App\Enums\System\GenderEnum;
use App\Rules\IranMobilePhoneRule;
use App\Rules\NationalcodeRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreStudentApiRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name'       => ['required', 'string', 'max:255'],
            'last_name'        => ['required', 'string', 'max:255'],
            'national_code'    => ['required', new NationalcodeRule()],
            'father_name'      => ['required', 'string', 'max:255'],
            'gender'           => ['required', new Enum(GenderEnum::class)],
            'phone'            => ['required', 'numeric', new IranMobilePhoneRule()],
            'phone2'           => 'nullable|numeric',
            'phone2_owner'     => ['nullable', Rule::enum(RelationTypeEnum::class)],
            'education_level'  => ['nullable', new Enum(EducationLevelEnum::class)],
            'education_status' => ['nullable', new Enum(EducationStatusEnum::class)],
            'field_of_study'   => 'nullable|string',
            'date_of_birth'    => 'required|date',
            'update_student'   => 'required|boolean',
        ];
    }

    public function messages()
    {
        return [
            'user_id.required_if' => __('validation.custom.student.new_user'),
        ];
    }
}
