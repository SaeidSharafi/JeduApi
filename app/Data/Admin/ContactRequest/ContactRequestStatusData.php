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

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'status' => [
                'description' => 'The new request status. Available values: `pending`, `contacted`, `resolved`, `no_response`.',
                'example'     => InboundRequestStatusEnum::CONTACTED->value,
            ],
        ];
    }
}
