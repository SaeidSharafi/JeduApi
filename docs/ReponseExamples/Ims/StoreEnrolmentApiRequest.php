<?php

declare(strict_types=1);

namespace App\Http\Requests\Students;

use App\Enums\Financial\DiscountTypeEnum;
use Illuminate\Foundation\Http\FormRequest;

final class StoreEnrolmentApiRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
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
            'course_code' => ['required', 'exists:courses,code'],
            'payment'     => [
                'required', 'array',
                'required_array_keys:amount,tracking_code,date,bank_account_number,discount_type,discount_amount',
            ],
            'payment.amount'              => ['required', 'integer'],
            'payment.tracking_code'       => ['required', 'string'],
            'payment.date'                => ['required', 'date'],
            'payment.bank_account_number' => ['nullable', 'string'],
            'payment.discount_type'       => ['required', 'string'],
            'payment.discount_amount'     => [
                'required_unless:discount_type,'.DiscountTypeEnum::NONE->value, 'nullable', 'integer',
            ],
            'payment.discount_code' => ['nullable', 'string'],
            'note'                  => ['nullable', 'string'],
        ];
    }
}
