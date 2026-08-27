<?php

declare(strict_types=1);

namespace App\Data\Admin\ContactRequest;

use App\Enums\InboundRequestStatusEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

final class ContactRequestStatusData extends Data
{
    public function __construct(public InboundRequestStatusEnum $status) {}

    public static function rules(): array
    {
        return ['status' => ['required', 'string', Rule::enum(InboundRequestStatusEnum::class)]];
    }
}
